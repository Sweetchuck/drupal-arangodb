<?php

declare(strict_types = 1);

namespace Drupal\arangodb\Plugin\AdvancedQueue\Backend;

use ArangoDBClient\Cursor;
use ArangoDBClient\Exception as ArangoDBException;
use ArangoDBClient\ServerException as ArangoDBServerException;
use ArangoDBClient\Statement;
use Drupal\advancedqueue\Job;
use Drupal\advancedqueue\Plugin\AdvancedQueue\Backend\BackendBase;
use Drupal\advancedqueue\Plugin\AdvancedQueue\Backend\SupportsDeletingJobsInterface;
use Drupal\advancedqueue\Plugin\AdvancedQueue\Backend\SupportsListingJobsInterface;
use Drupal\advancedqueue\Plugin\AdvancedQueue\Backend\SupportsLoadingJobsInterface;
use Drupal\advancedqueue\Plugin\AdvancedQueue\Backend\SupportsReleasingJobsInterface;
use Drupal\arangodb\ConnectionFactoryInterface;
use Drupal\arangodb\ConnectionTrait;
use Drupal\arangodb\Queue\AdvancedDocumentConverterInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Form\FormStateInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Sweetchuck\CacheBackend\ArangoDb\SchemaManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Advanced Queue Backend plugin implementation with ArangoDB.
 *
 * @AdvancedQueueBackend(
 *   id = "arangodb",
 *   label = @Translation("ArangoDB"),
 * )
 */
class Arangodb extends BackendBase implements
  LoggerAwareInterface,
  SupportsReleasingJobsInterface,
  SupportsDeletingJobsInterface,
  SupportsLoadingJobsInterface,
  SupportsListingJobsInterface {

  use LoggerAwareTrait;
  use ConnectionTrait;

  protected AdvancedDocumentConverterInterface $documentConverter;

  public function getDocumentConverter(): AdvancedDocumentConverterInterface {
    return $this->documentConverter;
  }

  public function setDocumentConverter(AdvancedDocumentConverterInterface $documentConverter): static {
    $this->documentConverter = $documentConverter;

    return $this;
  }

  public function getLogger(): ?LoggerInterface {
    return $this->logger;
  }

  /**
   * @phpstan-param drupal-arangodb-advancedqueue-config $configuration
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('datetime.time'),
      $container->get('arangodb.connection_factory'),
      $container->get('arangodb.queue_advanced.schema_manager.default'),
      $container->get('arangodb.queue_advanced.document_converter.default'),
    );
  }

  /**
   * @phpstan-param drupal-arangodb-advancedqueue-config $configuration
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    TimeInterface $time,
    ConnectionFactoryInterface $connectionFactory,
    SchemaManagerInterface $schemaManager,
    AdvancedDocumentConverterInterface $documentConverter,
  ) {
    $this->dbConnectionFactory = $connectionFactory;
    $this
      ->setSchemaManager($schemaManager)
      ->setDocumentConverter($documentConverter);
    parent::__construct($configuration, $plugin_id, $plugin_definition, $time);
  }

  public function getQueueName(): string {
    return $this->queueId;
  }

  /**
   * @phpstan-return array<string, string>
   */
  public function getDbCollectionNamePlaceholderValues(): array {
    return [
      '{{ queue.name }}' => $this->getQueueName(),
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param drupal-arangodb-advancedqueue-config $configuration
   *
   * @return void
   */
  public function setConfiguration(array $configuration) {
    parent::setConfiguration($configuration);

    $configuration = $this->getConfiguration();
    $this->setDbCollectionNamePattern($configuration['storage_options']['collection_name_pattern']);
    $this->setDbConnection($this->dbConnectionFactory->get($configuration['connection_name']));
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-return drupal-arangodb-advancedqueue-config
   */
  public function defaultConfiguration() {
    $values = parent::defaultConfiguration();
    $values += [
      'connection_name' => 'default',
      'storage_options' => [
        'collection_name_pattern' => 'queue_advanced_shared',
      ],
      'threshold' => [
        Job::STATE_SUCCESS => [
          'type' => 'amount',
          'amount' => 100,
          'time' => 3,
        ],
        Job::STATE_FAILURE => [
          'type' => 'amount',
          'amount' => 100,
          'time' => 3,
        ],
      ],
    ];

    /** @var drupal-arangodb-advancedqueue-config $values */
    return $values;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $form
   *
   * @phpstan-return array<string, mixed>
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $parents = $form['#parents'];
    $namePrefix = $parents[0];
    if (count($parents) > 1) {
      $namePrefix .= '[' . implode('][', array_slice($parents, 1)) . ']';
    }

    $config = $this->getConfiguration();

    $form = parent::buildConfigurationForm($form, $form_state);
    $form['connection_name'] = [
      '#type' => 'select',
      '#required' => TRUE,
      '#title' => $this->t('Connection name'),
      '#description' => $this->t("Name of the ArangoDB connection defined in <code>arangodb.connection_options</code> service parameter or in <code>\$settings['arangodb.connection_options']</code> array in <code>settings.php</code>."),
      '#empty_option' => $this->t('- Select -'),
      '#options' => $this->dbConnectionFactory->getConnectionNames(),
      '#default_value' => $config['connection_name'],
    ];

    $form['storage_options'] = [
      '#type' => 'details',
      '#tree' => TRUE,
      '#title' => $this->t('Storage options'),
      '#open' => TRUE,

      'collection_name_pattern' => [
        '#type' => 'textfield',
        '#required' => TRUE,
        '#title' => $this->t('Collection name pattern'),
        '#default_value' => $config['storage_options']['collection_name_pattern'],
        '#description' => [
          'available_placeholders' => [
            '#theme' => 'item_list',
            '#title' => $this->t('Available placeholders:'),
            '#items' => [
              [
                '#markup' => '{{ queue.name }}',
              ],
            ],
          ],
          'recommended values' => [
            '#theme' => 'item_list',
            '#title' => $this->t('Recommended values:'),
            '#items' => [
              [
                '#markup' => 'queue_advanced_shared',
              ],
              [
                '#markup' => 'queue_advanced_dedicated_{{ queue.name }}',
              ],
            ],
          ],
        ],
      ],
    ];

    $form['threshold'] = [
      '#type' => 'details',
      '#tree' => TRUE,
      '#title' => $this->t('Threshold'),
      '#open' => TRUE,
      Job::STATE_SUCCESS => [
        '#type' => 'details',
        '#tree' => TRUE,
        '#title' => $this->t('Job state: success'),
        '#open' => TRUE,

        'type' => [
          '#type' => 'select',
          '#title' => $this->t('Type of the cleanup for the finished jobs'),
          '#default_value' => $config['threshold'][Job::STATE_SUCCESS]['type'],
          '#options' => $this->getCleanupTypeOptions(),
        ],
        'amount' => [
          '#type' => 'number',
          '#title' => $this->t('Number of successfully finished jobs to keep'),
          '#min' => 0,
          '#step' => 10,
          '#default_value' => $config['threshold'][Job::STATE_SUCCESS]['amount'],
          '#states' => [
            'visible' => [
              ":input[name='{$namePrefix}[threshold][success][type]']" => ['value' => 'amount'],
            ],
          ],
        ],
        'time' => [
          '#type' => 'number',
          '#title' => $this->t('Number of days to keep the jobs for'),
          '#min' => 0,
          '#step' => 1,
          '#default_value' => $config['threshold'][Job::STATE_SUCCESS]['time'],
          '#states' => [
            'visible' => [
              ":input[name='{$namePrefix}[threshold][success][type]']" => ['value' => 'time'],
            ],
          ],
        ],
      ],
      Job::STATE_FAILURE => [
        '#type' => 'details',
        '#tree' => TRUE,
        '#title' => $this->t('Job state: failure'),
        '#open' => TRUE,

        'type' => [
          '#type' => 'select',
          '#title' => $this->t('Type of the cleanup for the failed jobs'),
          '#default_value' => $config['threshold'][Job::STATE_FAILURE]['type'],
          '#options' => $this->getCleanupTypeOptions(),
        ],
        'amount' => [
          '#type' => 'number',
          '#title' => $this->t('Number of failed jobs to keep'),
          '#min' => 0,
          '#step' => 10,
          '#default_value' => $config['threshold'][Job::STATE_FAILURE]['amount'],
          '#states' => [
            'visible' => [
              ":input[name='{$namePrefix}[threshold][failure][type]']" => ['value' => 'amount'],
            ],
          ],
        ],
        'time' => [
          '#type' => 'number',
          '#title' => $this->t('Number of days to keep the jobs for'),
          '#min' => 0,
          '#step' => 1,
          '#default_value' => $config['threshold'][Job::STATE_FAILURE]['time'],
          '#states' => [
            'visible' => [
              ":input[name='{$namePrefix}[threshold][failure][type]']" => ['value' => 'time'],
            ],
          ],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $form
   *
   * @return void
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);

    $parents = $form['#parents'];
    $element_name_prefix = implode('][', $parents);
    if ($element_name_prefix) {
      $element_name_prefix .= '][';
    }

    $values = $form_state->getValue($form['#parents']);
    try {
      $this->dbConnectionFactory->get($values['connection_name']);
    }
    catch (\Exception $exception) {
      $form_state->setErrorByName(
         "{$element_name_prefix}connection_name",
        (string) $this->t(
          'Connection with name %connection_name could not be established. Error message: @error_message',
          [
            '%connection_name' => $values['connection_name'],
            '@error_message' => $exception->getMessage(),
          ],
        ),
      );
    }
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $form
   *
   * @return void
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if ($form_state->getErrors()) {
      return;
    }

    $values = $form_state->getValue($form['#parents']);
    $this->configuration['connection_name'] = $values['connection_name'];
    $this->configuration['storage_options'] = $values['storage_options'];
    $this->configuration['threshold'] = $values['threshold'];
  }

  /**
   * @phpstan-return array<string, string|\Drupal\Core\StringTranslation\TranslatableMarkup>
   */
  protected function getCleanupTypeOptions(): array {
    return [
      'none' => $this->t('Keep all jobs'),
      'amount' => $this->t('Keep certain amount of jobs'),
      'time' => $this->t('Keep jobs for certain amount of days'),
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @return void
   *
   * @throws \ArangoDBClient\Exception
   */
  public function createQueue() {
    $this
      ->initDbConnection()
      ->initDbCollection($this->getDbCollectionName());
  }

  /**
   * {@inheritdoc}
   *
   * @return void
   *
   * @throws \ArangoDBClient\Exception
   */
  public function cleanupQueue() {
    $collectionName = $this->getDbCollectionName();
    $this
      ->initDbConnection()
      ->initDbCollection($collectionName)
      ->releaseStuckJobs()
      ->cleanupFinishedJobs();
  }

  /**
   * @throws \ArangoDBClient\Exception
   */
  protected function cleanupFinishedJobs(): static {
    $config = $this->getConfiguration();
    $this
      ->cleanupFinishedJobsByState(Job::STATE_SUCCESS, $config['threshold'][Job::STATE_SUCCESS])
      ->cleanupFinishedJobsByState(Job::STATE_FAILURE, $config['threshold'][Job::STATE_FAILURE]);

    return $this;
  }

  /**
   * @phpstan-param drupal-arangodb-advancedqueue-config-treshold $threshold
   *
   * @throws \ArangoDBClient\Exception
   */
  protected function cleanupFinishedJobsByState(string $state, array $threshold): static {
    $query = '';
    $bindVars = [];
    switch ($threshold['type']) {
      case 'amount':
        $amountToKeep = $threshold['amount'];
        $numOfJobs = $this->countJobs();
        if ($numOfJobs[$state] === 0
          || ($amountToKeep && $numOfJobs[$state] <= $amountToKeep)
        ) {
          // There are no any jobs to delete
          // or the available jobs are less than the limit.
          break;
        }

        $query = <<< AQL
        FOR doc IN @@collection
          FILTER
            doc.queueId == @queueId
            AND
            doc.state == @state
          SORT
            doc.processed
          LIMIT @limitCount
          REMOVE doc IN @@collection
        AQL;

        $bindVars = [
          '@collection' => $this->getDbCollectionName(),
          'queueId' => $this->queueId,
          'state' => $state,
          'limitCount' => $numOfJobs[$state] - $amountToKeep,
        ];
        break;

      case 'time':
        $query = <<< AQL
        FOR doc IN @@collection
          FILTER
            doc.queueId == @queueId
            AND
            doc.state == @state
            AND
            doc.processed < @processed
          REMOVE doc IN @@collection
        AQL;

        $bindVars = [
          '@collection' => $this->getDbCollectionName(),
          'queueId' => $this->queueId,
          'state' => $state,
          'processed' => $this->time->getCurrentTime() - ($threshold['time'] * 86400),
        ];
        break;
    }

    if (!$query) {
      return $this;
    }

    $this->executeStatement($query, $bindVars);

    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * @return void
   *
   * @throws \ArangoDBClient\Exception
   */
  public function deleteQueue() {
    $this->initDbConnection();

    $collectionName = $this->getDbCollectionName();
    if (!$this->dbCollectionHandler->has($collectionName)) {
      // Nothing to delete.
      return;
    }

    if (!$this->isShared()) {
      // Collection is dedicated only for this $queueName.
      $this->dbCollectionHandler->drop($collectionName);

      return;
    }

    // Collection can not be deleted, because the storage is shared,
    // and maybe other process has had already called the ::createQueue()
    // with a different $queueName,
    // but has not added any jobs to queue yet.
    $this->deleteJobs();
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-return drupal-advancedqueue-count-jobs-report
   *
   * @throws \ArangoDBClient\Exception
   */
  public function countJobs() {
    $collectionName = $this->getDbCollectionName();
    $this
      ->initDbConnection()
      ->initDbCollection($collectionName);

    $values = [
      Job::STATE_QUEUED => 0,
      Job::STATE_PROCESSING => 0,
      Job::STATE_SUCCESS => 0,
      Job::STATE_FAILURE => 0,
    ];

    $query = <<< AQL
    FOR doc IN @@collection
      FILTER
        doc.queueId == @queueId
      COLLECT
        state = doc.state WITH COUNT INTO length
      RETURN {
        "state": state,
        "count": length
      }
    AQL;

    $result = $this->executeStatement(
      $query,
      [
        '@collection' => $collectionName,
        'queueId' => $this->queueId,
      ],
    );

    /** @var \ArangoDBClient\Document $row */
    foreach ($result as $row) {
      /** @var drupal-advancedqueue-job-state $state */
      $state = (string) $row->get('state');
      $values[$state] = (int) $row->get('count');
    }

    return $values;
  }

  /**
   * {@inheritdoc}
   *
   * @return void
   *
   * @throws \ArangoDBClient\Exception
   */
  public function enqueueJob(Job $job, $delay = 0) {
    $this->enqueueJobs([$job], $delay);
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<\Drupal\advancedqueue\Job> $jobs
   *
   * @return void
   *
   * @throws \ArangoDBClient\Exception
   */
  public function enqueueJobs(array $jobs, $delay = 0) {
    $collectionName = $this->getDbCollectionName();

    $this
      ->initDbConnection()
      ->initDbCollection($collectionName);

    // @todo Support transactions.
    // @todo Configurable batch size.
    $batchSize = 1000;
    $documentConverter = $this->getDocumentConverter();
    /** @var array<\Drupal\advancedqueue\Job> $current_jobs_set */
    $current_jobs_set = array_splice($jobs, 0, $batchSize);
    while ($current_jobs_set) {
      $documents = [];
      foreach ($current_jobs_set as $job) {
        if ($job->getQueueId() !== $this->queueId) {
          $job->setQueueId($this->queueId);
          $job->setId('');
        }

        $job->setState(Job::STATE_QUEUED);
        if (!$job->getAvailableTime()) {
          $job->setAvailableTime($this->time->getCurrentTime() + $delay);
        }
        $documents[] = $documentConverter->jobToDocument($collectionName, $job);
      }

      $response = $this->dbDocumentHandler->insertMany($this->dbCollection, $documents);
      foreach ($response as $index => $row) {
        $current_jobs_set[$index]->setId($row['_key']);
      }

      $current_jobs_set = array_splice($jobs, 0, $batchSize);
    }
  }

  /**
   * {@inheritdoc}
   *
   * @return void
   *
   * @throws \ArangoDBClient\Exception
   */
  public function retryJob(Job $job, $delay = 0) {
    if ($job->getState() !== Job::STATE_FAILURE) {
      throw new \InvalidArgumentException(sprintf(
        'Only failed jobs can be retried. queue: %s; type %s; id: %s; state: %s',
        $job->getQueueId(),
        $job->getType(),
        $job->getId(),
        $job->getState(),
      ));
    }

    $collectionName = $this->getDbCollectionName();

    $this
      ->initDbConnection()
      ->initDbCollection($collectionName);

    $job->setNumRetries($job->getNumRetries() + 1);
    $job->setAvailableTime($this->time->getCurrentTime() + $delay);
    $job->setState(Job::STATE_QUEUED);
    $this
      ->dbDocumentHandler
      ->update($this->documentConverter->jobToDocument($collectionName, $job));
  }

  /**
   * {@inheritdoc}
   *
   * @throws \ArangoDBClient\Exception
   */
  public function claimJob() {
    $this
      ->initDbConnection()
      ->initDbCollection($this->getDbCollectionName());

    $collectionName = $this->getDbCollectionName();

    $query = <<< AQL
      FOR doc IN @@collection
        FILTER
          doc.queueId == @queueId
          AND
          doc.state == @state
          AND
          doc.available <= @now
          AND
          doc.expires == 0
        SORT
            doc.available,
            doc._key
        LIMIT 0, 1
        RETURN doc
    AQL;

    // Claim an item by updating its expire fields. If claim is not successful
    // another thread may have claimed the item in the meantime. Therefore loop
    // until an item is successfully claimed or we are reasonably sure there
    // are no unclaimed items left.
    while (TRUE) {
      $result = $this->executeStatement(
        $query,
        [
          '@collection' => $collectionName,
          'queueId' => $this->queueId,
          'state' => Job::STATE_QUEUED,
          'now' => $this->time->getCurrentTime(),
        ],
      );

      $result->rewind();
      if (!$result->valid()) {
        return NULL;
      }

      /** @var null|\ArangoDBClient\Document $document */
      $document = $result->current();
      if (!$document) {
        return NULL;
      }

      $job = $this
        ->getDocumentConverter()
        ->documentToJob($document);

      // Try to update the job. Only one thread can succeed in UPDATEing the
      // same row. We cannot rely on REQUEST_TIME because jobs might be
      // claimed by a single consumer which runs longer than 1 second. If we
      // continue to use REQUEST_TIME instead of the current time(), we steal
      // time from the lease, and will tend to reset items before the lease
      // should really expire.
      $document->set('state', Job::STATE_PROCESSING);
      $document->set('expires', $this->time->getCurrentTime() + $this->configuration['lease_time']);

      try {
        $this->dbDocumentHandler->update($document);
        $job->setState($document->get('state'));
        $job->setExpiresTime($document->get('expires'));

        return $job;
      }
      catch (ArangoDBException $e) {
        // Continue.
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * @return void
   *
   * @throws \ArangoDBClient\Exception
   */
  public function onSuccess(Job $job) {
    $collectionName = $this->getDbCollectionName();

    $this
      ->initDbConnection()
      ->initDbCollection($collectionName);

    $job->setProcessedTime($this->time->getCurrentTime());
    $this
      ->dbDocumentHandler
      ->update($this->documentConverter->jobToDocument($collectionName, $job));
  }

  /**
   * {@inheritdoc}
   *
   * @return void
   *
   * @throws \ArangoDBClient\Exception
   */
  public function onFailure(Job $job) {
    $collectionName = $this->getDbCollectionName();

    $this
      ->initDbConnection()
      ->initDbCollection($collectionName);

    $job->setState(Job::STATE_FAILURE);
    $job->setProcessedTime($this->time->getCurrentTime());
    $this
      ->dbDocumentHandler
      ->update($this->documentConverter->jobToDocument($collectionName, $job));
  }

  /**
   * {@inheritdoc}
   *
   * @return void
   *
   * @throws \ArangoDBClient\Exception
   */
  public function releaseJob($job_id) {
    $this
      ->initDbConnection()
      ->initDbCollection($this->getDbCollectionName());

    $query = <<< AQL
      UPDATE {
        _key: @key,
        state: @state,
        expires: @expires
      }
      IN @@collection
    AQL;

    try {
      $this->executeStatement(
        $query,
        [
          '@collection' => $this->getDbCollectionName(),
          'key' => $job_id,
          'state' => Job::STATE_QUEUED,
          'expires' => 0,
        ],
      );
    }
    catch (ArangoDBException $e) {
      // @todo Error log.
    }
  }

  /**
   * @throws \ArangoDBClient\Exception
   */
  public function releaseStuckJobs(): static {
    $this
      ->initDbConnection()
      ->initDbCollection($this->getDbCollectionName());

    $query = <<< AQL
    FOR doc IN @@collection
      FILTER
        doc.queueId == @queueId
        AND
        doc.expires > 0
        AND
        doc.expires <= @now
        AND
        doc.state == @stateCurrent
      UPDATE doc WITH {
        state: @stateNew,
        expires: 0
      }
      IN @@collection
    AQL;

    try {
      $this->executeStatement(
        $query,
        [
          '@collection' => $this->getDbCollectionName(),
          '@queueId' => $this->queueId,
          'now' => $this->time->getCurrentTime(),
          'stateCurrent' => Job::STATE_PROCESSING,
          'stateNew' => Job::STATE_QUEUED,
        ],
      );
    }
    catch (ArangoDBException) {
      // @todo Error log.
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * @return void
   *
   * @throws \ArangoDBClient\Exception
   */
  public function deleteJob($job_id) {
    $collectionName = $this->getDbCollectionName();
    $this
      ->initDbConnection()
      ->initDbCollection($collectionName);

    try {
      $this->dbDocumentHandler->removeById($collectionName, $job_id);
    }
    catch (ArangoDBServerException $e) {
      $details = $e->getDetails();
      if (isset($details['code']) && $details['code'] === 404) {
        // The "errorNum" is int(1202).
        return;
      }

      throw $e;
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \ArangoDBClient\Exception
   */
  public function loadJob($job_id) {
    $this
      ->initDbConnection()
      ->initDbCollection($this->getDbCollectionName());

    try {
      $document = $this->dbDocumentHandler->getById(
        $this->dbCollection->getName(),
        $job_id,
      );
    }
    catch (ArangoDBException $exception) {
      // @todo Check the error code.
      throw new \InvalidArgumentException('', 404, $exception);
    }

    return $this
      ->getDocumentConverter()
      ->documentToJob($document);
  }

  /**
   * Is this storage (ArangoDB collection) handles more than one queue?
   */
  protected function isShared(): bool {
    return !str_contains(
      $this->getDbCollectionNamePattern(),
      '{{ queue.name }}',
    );
  }

  /**
   * @throws \ArangoDBClient\Exception
   */
  protected function deleteJobs(): static {
    $query = <<< AQL
      FOR doc IN @@collection
        FILTER
          doc.queueId == @queueId
        REMOVE doc._key IN @@collection
    AQL;

    $this->executeStatement(
      $query,
      [
        '@collection' => $this->getDbCollectionName(),
        'queueId' => $this->queueId,
      ],
    );

    return $this;
  }

  /**
   * @param array<string, mixed> $bindVars
   *
   * @throws \ArangoDBClient\Exception
   */
  protected function executeStatement(string $query, array $bindVars): Cursor {
    $statement = new Statement(
      $this->dbConnection,
      [
        'query' => $query,
        'bindVars' => $bindVars,
      ] + $this->getExecuteStatementData(),
    );

    $statement->setDocumentClass($this->documentConverter->getDocumentClass());

    return $statement->execute();
  }

  /**
   * @phpstan-return cache-backend-arangodb-execute-statement-data
   * @psalm-return ExecuteStatementData
   */
  protected function getExecuteStatementData(): array {
    return [
      'batchSize' => 1000,
      'sanitize' => TRUE,
    ];
  }

}
