<?php

namespace Drupal\assistant;

use Drupal\Component\Datetime\DateTimePlus;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Entity\Entity;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Render\Renderer;
use Drupal\image\Entity\ImageStyle;
use Drupal\file\Entity\File;

/**
 * Contains generic helper methods.
 *
 * @package Drupal\assistant
 */
class Assistant {

  /**
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPath;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityManager;

  /**
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Path\CurrentPathStack $current_path
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   * @param \Drupal\Core\Datetime\DateFormatter $date_formatter
   * @param \Drupal\Core\Render\Renderer $renderer
   * @param \Drupal\Core\Image\ImageFactory $image_factory
   */
  public function __construct(CurrentPathStack $current_path, EntityTypeManagerInterface $entity_manager, DateFormatter $date_formatter, Renderer $renderer, ImageFactory $image_factory) {
    $this->currentPath = $current_path;
    $this->entityManager = $entity_manager;
    $this->dateFormatter = $date_formatter;
    $this->renderer = $renderer;
    $this->imageFactory = $image_factory;
  }

  /**
   * Get all entity ids which are related to the given taxonomy term
   *
   * @param $enitity_type
   * @param $bundle
   * @param $field
   * @param $tid
   *
   * @return array|int
   */
  public static function getTermEntities($enitity_type, $bundle, $field, $tid) {
    $query = \Drupal::entityQuery($enitity_type)
      ->condition('status', 1)
      ->condition('type', $bundle)
      ->condition($field, $tid);
    return $query->execute();
  }

  /**
   * Get current path arguments.
   *
   * @param bool $num
   *   Number of argument that needs to be returned.
   *
   * @return mixed
   *   Return array of arguments or specified argument
   */
  public function getArgs($num = FALSE) {
    $path_args = array_values(array_filter(explode('/', $this->currentPath->getPath())));

    if ($num) {
      return isset($path_args[$num]) ? $path_args[$num] : FALSE;
    }

    return $path_args;
  }

  /**
   * Get current path arguments.
   *
   * @param bool $path
   *   Path which will be used to fetch arguments
   *
   * @param bool $num
   *   Number of argument that needs to be returned.
   *
   * @return mixed
   *   Return array of arguments or specified argument
   */
  public function getArgsFromPath($path, $num = FALSE) {
    $path_args = array_values(array_filter(explode('/', $path)));

    if ($num) {
      return isset($path_args[$num]) ? $path_args[$num] : FALSE;
    }

    return $path_args;
  }

  /**
   * Return node by path alias.
   *
   * @param $path_alias string
   *
   * @return bool|\Drupal\Core\Entity\EntityInterface|\Drupal\node\Entity\Node|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  function getNodeByPathAlias($path_alias) {
    $path = \Drupal::service('path.alias_manager')->getPathByAlias($path_alias);
    if (preg_match('/node\/(\d+)/', $path, $matches)) {
      $node_store = $this->entityManager->getStorage('node');
      return $node_store->load($matches[1]);
    }
    return FALSE;
  }

  /**
   * Get current path alias arguments.
   *
   * @param bool $num
   *   Number of argument that needs to be returned.
   *
   * @return mixed
   *   Return array of arguments or specified argument
   */
  public function getAliasArgs($num = FALSE) {
    $path_args = array_values(array_filter(explode('/', \Drupal::request()
      ->getRequestUri())));

    if ($num) {
      return isset($path_args[$num]) ? $path_args[$num] : FALSE;
    }

    return $path_args;
  }

  /**
   * Make new format from an input format.
   *
   * @param string|int|null $value
   *   Input date can be string as a format, timestamp, or null.
   * @param string $format_in
   *   PHP date() type format for parsing the input.
   * @param string $format_out
   *   Drupal date format entry machine name.
   *
   * @return string
   *   Formatted date.
   */
  public function formatFromFormat($value, $format_in = NULL, $format_out) {
    $date = '';
    if (!empty($value)) {
      if (is_numeric($value) || $format_in === NULL) {
        $date = $this->dateFormatter->format($value, $format_out);
      }
      else {
        $timestamp = DateTimePlus::createFromFormat($format_in, $value)->getTimestamp();
        $date = $this->dateFormatter->format($timestamp, $format_out);
      }
    }
    return $date;
  }

  /**
   * Get text value from entity
   *
   * @param object $entity
   *   Parent entity. Can be node, commerce_product, user etc.
   * @param string $field
   *   The name of the image field in entity.
   * @param bool $html
   *    Determines type of returning value.
   *
   * @return string
   *    Html or text depending on what's the field value.
   */
  public static function getEntitySingleVal($entity, $field, $html = FALSE) {
    $result = '';
    if ($entity->hasField($field)) {
      $field = $entity->get($field)->getValue();
      if (!empty($field)) {
        $field = current($field);
        if ($html || !empty($field['format'])) {
          $result = check_markup($field['value'], $field['format']);
        }
        else {
          $result = $field['value'];
        }
      }
    }
    return $result;
  }

  /**
   * Get image form entity field.
   *
   * @param $file
   *   The name of the image field in entity.
   * @param string $type
   *   Format of the returning value. Can be: render, html, url.
   * @param string $image_style
   *   The image style name.
   * @param bool $responsive
   *   Whether to use responsive images module or not
   *
   * @return mixed
   *   Render array, html or uri.
   */
  public static function getFileImage($file, $image_style, $type = 'render', $responsive = TRUE) {

    if (is_numeric($file)) {
      $file = File::load($file);
    }

    if ($file instanceof File) {
      $img_uri = $file->getFileUri();
      if ($image_style && $type == 'url') {
        return _responsive_image_image_style_url($image_style, $img_uri);
      }

      /**
       * The image.factory service will check if our image is valid.
       *
       * @var \Drupal\Core\Image\Image $image
       */
      $image = \Drupal::service('image.factory')->get($img_uri);

      if ($image->isValid()) {
        $width = $image->getWidth();
        $height = $image->getHeight();
      }
      else {
        $width = $height = NULL;
      }

      $build = [
        '#width' => $width,
        '#height' => $height,
        '#uri' => $img_uri,
        '#attributes' => [
          'alt' => $file->get('field_image_alt_text')->value,
          'title' => $file->get('field_image_title_text')->value,
        ],
      ];

      // Check if it's an Image style or Responsive image
      $entity_image_style = ImageStyle::load($image_style);
      if ($entity_image_style instanceof ImageStyle) {
        $responsive = FALSE;
      }

      if ($responsive) {
        $build['#theme'] = 'responsive_image';
        $build['#responsive_image_style_id'] = $image_style;
      }
      else {
        $build['#theme'] = 'image_style';
        $build['#style_name'] = $image_style;
      }

      $renderer = \Drupal::service('renderer');
      $renderer->addCacheableDependency($build, $file);

      if ($type == 'render') {
        return $build;
      }

      if ($type == 'html') {
        return $renderer->renderRoot($build);
      }

    }

    return FALSE;
  }

  /**
   * Get image form entity field.
   *
   * @param object $entity
   *   Parent entity. Can be node, commerce_product, user etc.
   * @param string $field
   *   The name of the image field in entity.
   * @param string $type
   *   Format of the returning value. Can be: render, html, url.
   * @param string $image_style
   *   The image style name.
   * @param bool $responsive
   *   Whether to use responsive images module or not
   *
   * @return mixed
   *   Render array, html or uri.
   */
  public static function getEntityImage($entity, $field, $image_style, $type = 'render', $responsive = TRUE) {

    if (!empty($entity->get($field)[0])) {
      $file = $entity->get($field)[0]->entity;
      return self::getFileImage($file, $image_style, $type, $responsive);
    }

    return FALSE;
  }

  /**
   * Get entity tags.
   *
   * @param array
   *   Entity objects or entity ids
   * @param string $entity_type
   *   Entity type.
   * @param array $tags
   *  Pass to update existing tags with new values.
   *
   * @return array Tags.
   * Tags.
   */
  public static function getEntityCacheTags($entities, $entity_type, &$tags = []) {
    /** @var \Drupal\Core\Entity\Entity $entity */
    foreach ($entities as $entity) {
      $id = $entity instanceof Entity ? $entity->id() : $entity;
      $tags[] = $entity_type . ':' . $id;
    }
    return $tags;
  }

  /**
   * Get menu tree.
   *
   * @param $menu_name
   *
   * @return array.
   */
  public static function getMenuTree($menu_name) {
    // Load admin menu tree.
    $menu_tree = \Drupal::menuTree();
    $parameters = new MenuTreeParameters();
    $parameters->onlyEnabledLinks()->setTopLevelOnly();

    $tree = $menu_tree->load($menu_name, $parameters);

    $manipulators = [
      // Only show links that are accessible for the current user.
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      // Use the default sorting of menu links.
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $menu_tree->transform($tree, $manipulators);
    // Finally, build a render array from the transformed tree.
    return $menu_tree->build($tree);
  }

}
