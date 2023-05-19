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
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
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
  use ContainerAwareTrait;
  use ConnectionTrait;

  protected ConnectionFactoryInterface $connectionFactory;

  protected AdvancedDocumentConverterInterface $documentConverter;

  public function getDocumentConverter(): AdvancedDocumentConverterInterface {
    return $this->documentConverter;
  }

  public function setDocumentConverter(AdvancedDocumentConverterInterface $documentConverter): static {
    $this->documentConverter = $documentConverter;

    return $this;
  }

  protected SchemaManagerInterface $schemaManager;

  public function getSchemaManager(): SchemaManagerInterface {
    return $this->schemaManager;
  }

  public function setSchemaManager(SchemaManagerInterface $schemaManager): static {
    $this->schemaManager = $schemaManager;

    return $this;
  }

  public function getLogger(): ?LoggerInterface {
    return $this->logger;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('datetime.time'),
      $container->get('arangodb.queue_advanced.schema_manager.default'),
      $container->get('arangodb.queue_advanced.document_converter.default'),
      $container,
    );
  }

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    TimeInterface $time,
    SchemaManagerInterface $schemaManager,
    AdvancedDocumentConverterInterface $documentConverter,
    ContainerInterface $container,
  ) {
    $this->setContainer($container);
    $this
      ->setSchemaManager($schemaManager)
      ->setDocumentConverter($documentConverter);
    parent::__construct($configuration, $plugin_id, $plugin_definition, $time);
  }

  /**
   * Service name prefix.
   *
   * @var string
   */
  protected string $connectionFactoryPrefix = 'arangodb.connection_factory';

  protected function getConnectionFactoryPrefix(): string {
    return $this->connectionFactoryPrefix;
  }

  protected function getConnectionFactoryServiceName(?string $suffix = NULL): string {
    if ($suffix === NULL) {
      $suffix = $this->getConfiguration()['connection_factory'];
    }

    return $this->getConnectionFactoryPrefix() . ".$suffix";
  }

  public function getQueueName(): string {
    return $this->queueId;
  }

  public function getCollectionNamePlaceholderValues(): array {
    return [
      '{{ queue.name }}' => $this->getQueueName(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    parent::setConfiguration($configuration);

    $configuration = $this->getConfiguration();
    $this->setCollectionNamePattern($configuration['storage_options']['collection_name_pattern']);
    $this->connectionFactory = $this->container->get($this->getConnectionFactoryServiceName());
    $this->setConnection($this->connectionFactory->get());
  }

  public function defaultConfiguration() {
    $values = parent::defaultConfiguration();
    $values += [
      'connection_factory' => 'default',
      'storage_options' => [
        'collection_name_pattern' => 'queue_advanced_shared',
      ],
    ];

    return $values;
  }

  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $config = $this->getConfiguration();

    $form = parent::buildConfigurationForm($form, $form_state);
    $form['connection_factory'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Connection factory'),
      '#default_value' => $config['connection_factory'],
      '#field_prefix' => $this->getConnectionFactoryPrefix() . '.',
      '#description' => $this->t('Last part of a service name <code>arangodb.connection_factory.*</code>'),
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

    return $form;
  }

  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);

    $parents = $form['#parents'];
    $element_name_prefix = implode('][', $parents);
    if ($element_name_prefix) {
      $element_name_prefix .= '][';
    }

    $values = $form_state->getValue($form['#parents']);
    $connection_factory_service_name = $this->getConnectionFactoryServiceName($values['connection_factory']);
    if (!$this->container->has($connection_factory_service_name)) {
      $form_state->setErrorByName(
         "{$element_name_prefix}connection_factory",
        $this->t(
          'Service name does not exists: @service_name',
          [
            '@service_name' => $connection_factory_service_name,
          ],
        ),
      );
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \ArangoDBClient\Exception
   */
  public function createQueue() {
    $this
      ->initConnection()
      ->initCollection($this->getCollectionName());
  }

  /**
   * {@inheritdoc}
   *
   * @throws \ArangoDBClient\Exception
   */
  public function deleteQueue() {
    $this->initConnection();

    $collectionName = $this->getCollectionName();
    if (!$this->collectionHandler->has($collectionName)) {
      // Nothing to delete.
      return;
    }

    if (!$this->isShared()) {
      // Collection is dedicated only for this $queueName.
      $this->collectionHandler->drop($collectionName);

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
   * @throws \ArangoDBClient\Exception
   */
  public function countJobs() {
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
        '@collection' => $this->getCollectionName(),
        'queueId' => $this->queueId,
      ],
    );

    /** @var \ArangoDBClient\Document $row */
    foreach ($result as $row) {
      $values[$row->get('state')] = $row->get('count');
    }

    return $values;
  }

  /**
   * {@inheritdoc}
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
   * @throws \ArangoDBClient\Exception
   */
  public function enqueueJobs(array $jobs, $delay = 0) {
    $collectionName = $this->getCollectionName();

    $this
      ->initConnection()
      ->initCollection($collectionName);

    // @todo Support transactions.
    $documentConverter = $this->getDocumentConverter();
    /** @var array<\Drupal\advancedqueue\Job> $current_jobs_set */
    while (($current_jobs_set = array_splice($jobs, 0, 1000))) {
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

      $response = $this->documentHandler->insertMany($this->collection, $documents);
      foreach ($response as $index => $row) {
        $current_jobs_set[$index]->setId($row['_key']);
      }
    }
  }

  /**
   * {@inheritdoc}
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

    $collectionName = $this->getCollectionName();

    $this
      ->initConnection()
      ->initCollection($collectionName);

    $job->setNumRetries($job->getNumRetries() + 1);
    $job->setAvailableTime($this->time->getCurrentTime() + $delay);
    $job->setState(Job::STATE_QUEUED);
    $this
      ->documentHandler
      ->update($this->documentConverter->jobToDocument($collectionName, $job));
  }

  /**
   * {@inheritdoc}
   *
   * @throws \ArangoDBClient\Exception
   */
  public function claimJob() {
    $this
      ->initConnection()
      ->initCollection($this->getCollectionName());

    $collectionName = $this->getCollectionName();

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

      /** @var \ArangoDBClient\Document $document */
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
        $this->documentHandler->update($document);
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
   * @throws \ArangoDBClient\Exception
   */
  public function onSuccess(Job $job) {
    $collectionName = $this->getCollectionName();

    $this
      ->initConnection()
      ->initCollection($collectionName);

    $job->setProcessedTime($this->time->getCurrentTime());
    $this
      ->documentHandler
      ->update($this->documentConverter->jobToDocument($collectionName, $job));
  }

  /**
   * {@inheritdoc}
   *
   * @throws \ArangoDBClient\Exception
   */
  public function onFailure(Job $job) {
    $collectionName = $this->getCollectionName();

    $this
      ->initConnection()
      ->initCollection($collectionName);

    $job->setState(Job::STATE_FAILURE);
    $job->setProcessedTime($this->time->getCurrentTime());
    $this
      ->documentHandler
      ->update($this->documentConverter->jobToDocument($collectionName, $job));
  }

  /**
   * {@inheritdoc}
   *
   * @throws \ArangoDBClient\Exception
   */
  public function releaseJob($job_id) {
    $this
      ->initConnection()
      ->initCollection($this->getCollectionName());

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
          '@collection' => $this->getCollectionName(),
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
   * {@inheritdoc}
   *
   * @throws \ArangoDBClient\Exception
   */
  public function deleteJob($job_id) {
    $collectionName = $this->getCollectionName();
    $this
      ->initConnection()
      ->initCollection($collectionName);

    try {
      $this->documentHandler->removeById($collectionName, $job_id);
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
      ->initConnection()
      ->initCollection($this->getCollectionName());

    try {
      $document = $this->documentHandler->getById($this->collection, $job_id);
    }
    catch (ArangoDBException $e) {
      throw new \InvalidArgumentException('', 404, $e);
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
      $this->getCollectionNamePattern(),
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
        '@collection' => $this->getCollectionName(),
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
      $this->connection,
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
