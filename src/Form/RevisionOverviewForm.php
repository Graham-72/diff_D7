<?php

namespace Drupal\diff\Form;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Datetime\Date;
use Drupal\Component\Utility\String;
use Drupal\Core\Utility\LinkGenerator;

/**
 * Provides a form for revision overview page.
 */
class RevisionOverviewForm extends FormBase {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The date service.
   *
   * @var \Drupal\Core\Datetime\Date
   */
  protected $date;

  /**
   * The link generator service.
   *
   * @var \Drupal\Core\Utility\LinkGenerator
   */
  protected $link_generator;

  /**
   * Wrapper object for writing/reading simple configuration from diff.settings.yml
   */
  protected $config;


  /**
   * Constructs a RevisionOverviewForm object.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entityManager
   *   The entity manager.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Datetime\Date $date
   *   The date service.
   * @param \Drupal\Core\Utility\LinkGenerator
   *   The link generator service.
   * @param ConfigFactoryInterface $config_factory
   *   Config Factory service
   */
  public function __construct(EntityManagerInterface $entityManager, AccountInterface $currentUser, Date $date, LinkGenerator $link_generator, ConfigFactoryInterface $config_factory) {
    $this->entityManager = $entityManager;
    $this->currentUser = $currentUser;
    $this->date = $date;
    $this->link_generator = $link_generator;
    $this->config = $config_factory->get('diff.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager'),
      $container->get('current_user'),
      $container->get('date'),
      $container->get('link_generator'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'revision_overview_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state, $node = NULL) {
    $account = $this->currentUser;
    $node_storage = $this->entityManager->getStorage('node');
    $type = $node->getType();

    $build = array();
    $build['#title'] = $this->t('Revisions for %title', array('%title' => $node->label()));
    $build['nid'] = array(
      '#type' => 'hidden',
      '#value' => $node->nid->value,
    );

    $header = array($this->t('Revision'), '', '', $this->t('Operations'));

    $revert_permission = ((
        $account->hasPermission("revert $type revisions") ||
        $account->hasPermission('revert all revisions') ||
        $account->hasPermission('administer nodes')) &&
      $node->access('update')
    );
    $delete_permission = ((
        $account->hasPermission("delete $type revisions") ||
        $account->hasPermission('delete all revisions') ||
        $account->hasPermission('administer nodes')) &&
      $node->access('delete')
    );

    $rows = array();

    $vids = array_reverse($node_storage->revisionIds($node));
    // @todo We should take care of pagination in the future.
    foreach ($vids as $vid) {
      if ($revision = $node_storage->loadRevision($vid)) {
        $row = array();

        $revision_log = '';

        if ($revision->revision_log->value != '') {
          $revision_log = '<p class="revision-log">' . Xss::filter($revision->revision_log->value) . '</p>';
        }
        $username = array(
          '#theme' => 'username',
          '#account' => $revision->uid->entity,
        );
        $revision_date = $this->date->format($revision->getRevisionCreationTime(), 'short');

        // Current revision.
        if ($revision->isDefaultRevision()) {
          $row[] = array(
            'data' => $this->t('!date by !username', array(
                '!date' => $this->link_generator->generate($revision_date, 'node.view',array('node' => $node->id())),
                '!username' => drupal_render($username),
              )) . $revision_log,
            'class' => array('revision-current'),
          );
          // @todo If #value key is not provided a notice of undefined key appears.
          // I created issue https://drupal.org/node/2275837 for this bug
          // When resolved refactor this.
          $row[] = array(
            'data' => array(
              '#type' => 'radio',
              '#title_display' => 'invisible',
              '#name' => 'radios_left',
              '#return_value' => $vid,
              '#default_value' => FALSE,
            ),
          );
          $row[] = array(
            'data' => array(
              '#type' => 'radio',
              '#title_display' => 'invisible',
              '#name' => 'radios_right',
              '#default_value' => $vid,
              '#return_value' => $vid,
            ),
          );
          $row[] = array(
            'data' => String::placeholder($this->t('current revision')),
            'class' => array('revision-current')
          );
        }
        else {
          $row[] = $this->t('!date by !username', array(
              '!date' => $this->link_generator->generate($revision_date, 'node.revision_show', array(
                  'node' => $node->id(),
                  'node_revision' => $vid
                )),
              '!username' => drupal_render($username)
            )) . $revision_log;

          if ($revert_permission) {
            $links['revert'] = array(
              'title' => $this->t('Revert'),
              'route_name' => 'node.revision_revert_confirm',
              'route_parameters' => array(
                'node' => $node->id(),
                'node_revision' => $vid
              ),
            );
          }

          if ($delete_permission) {
            $links['delete'] = array(
              'title' => $this->t('Delete'),
              'route_name' => 'node.revision_delete_confirm',
              'route_parameters' => array(
                'node' => $node->id(),
                'node_revision' => $vid
              ),
            );
          }

          $row[] = array(
            'data' => array(
              '#type' => 'radio',
              '#title_display' => 'invisible',
              '#name' => 'radios_left',
              '#return_value' => $vid,
              '#default_value' => isset ($vids[1]) ? $vids[1] : FALSE,
            ),
          );
          $row[] = array(
            'data' => array(
              '#type' => 'radio',
              '#title_display' => 'invisible',
              '#name' => 'radios_right',
              '#return_value' => $vid,
              '#default_value' => FALSE,
            ),
          );
          $row[] = array(
            'data' => array(
              '#type' => 'operations',
              '#links' => $links,
            ),
          );
        }

        $rows[] = $row;
      }
    }

    $build['node_revisions_table'] = array(
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
      '#attributes' => array('class' => array('diff-revisions')),
      '#attached' => array(
        'js' => array(
          drupal_get_path('module', 'diff') . '/js/diff.js',
          array (
            'data' => array('diffRevisionRadios' => $this->config->get('general_settings.radio_behavior')),
            'type' => 'setting',
          ),
        ),
        'css' => array(
          drupal_get_path('module', 'diff') . '/css/diff.default.css',
        ),
      ),
    );

    $build['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Compare'),
      '#attributes' => array(
        'class' => array(
          'diff-button',
        ),
      ),
    );

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, array &$form_state) {
    $vid_left = $form_state['input']['radios_left'];
    $vid_right = $form_state['input']['radios_right'];
    if ($vid_left == $vid_right || !$vid_left || !$vid_right) {
      // @todo See why radio-boxes reset if there are errors.
      // @todo Follow the task 'Convert $form_state to an object and provide
      //   methods like setError()' (2225353).
      $this->setFormError('node_revisions_table', $form_state, $this->t('Select different revisions to compare.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    $vid_left = $form_state['input']['radios_left'];
    $vid_right = $form_state['input']['radios_right'];
    $nid = $form_state['input']['nid'];

    // Always place the older revision on the left side of the comparison
    // and the newer revision on the right side.
    if ($vid_left > $vid_right) {
      $aux = $vid_left;
      $vid_left = $vid_right;
      $vid_right = $aux;
    }

    $form_state['redirect'] = 'node/' . $nid . '/revisions/view/' . $vid_left . '/' . $vid_right;
  }

}
