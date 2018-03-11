<?php

namespace Drupal\assistant;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_cart\CartManagerInterface;


/**
 * Contains generic helper methods.
 *
 * @package Drupal\assistant
 */
class CommerceAssistant {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPath;

  /**
   * The cart manager.
   *
   * @var \Drupal\commerce_cart\CartManagerInterface
   */
  protected $cartManager;

  /**
   * The cart provider.
   *
   * @var \Drupal\commerce_cart\CartProviderInterface
   */
  protected $cartProvider;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityManager;

  /**
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Path\CurrentPathStack $current_path
   *   Represents the current path for the current request.
   * @param \Drupal\commerce_cart\CartManagerInterface $cart_manager
   * @param \Drupal\commerce_cart\CartProviderInterface $cart_provider
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   * @param \Drupal\Core\Routing\CurrentRouteMatch $route_match
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   */
  public function __construct(CurrentPathStack $current_path, CartManagerInterface $cart_manager, CartProviderInterface $cart_provider, EntityTypeManagerInterface $entity_manager, CurrentRouteMatch $route_match, CacheBackendInterface $cache) {
    $this->currentPath = $current_path;
    $this->cartManager = $cart_manager;
    $this->cartProvider = $cart_provider;
    $this->entityManager = $entity_manager;
    $this->routeMatch = $route_match;
    $this->cache = $cache;
  }

  /**
   * Get products of the current order.
   *
   * @return array
   */
  function getCurrentCartProducts() {
    $products = [];
    $order = $this->routeMatch->getParameter('commerce_order');
    if ($order) {
      $items = $order->getItems();
      if ($items) {
        foreach ($items as $item) {
          $purchasedEntity = $item->getPurchasedEntity();
          if ($purchasedEntity) {
            $products[] = $purchasedEntity->getProduct();
          }
        }
      }
    }
    return $products;
  }

  /**
   * Helper function to add product to the cart.
   *
   * @param $product_id
   * @param null $product_variation_id
   * @param null $store_id
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function addToCart($product_id, $product_variation_id = NULL, $store_id = NULL) {

    /** @var \Drupal\commerce_product\Entity\Product $product */
    $product = $this->entityManager->getStorage('commerce_product')->load($product_id);

    if ($product_variation_id === NULL) {
      $product_variation_id = $product->get('variations')
        ->getValue()[0]['target_id'];
    }

    if ($store_id === NULL) {
      $store_id = $product->get('stores')->getValue()[0]['target_id'];
    }

    /** @var \Drupal\commerce_product\Entity\ProductVariation $variation */
    $variation = $this->entityManager->getStorage('commerce_product_variation')
      ->load($product_variation_id);

    /** @var \Drupal\commerce_store\Entity\Store $store */
    $store = $this->entityManager->getStorage('commerce_store')
      ->load($store_id);

    $cart = $this->cartProvider->getCart('default', $store);

    if (!$cart) {
      $cart = $this->cartProvider->createCart('default', $store);
    }

    //$line_item_type_storage = $this->entityManager->getStorage('commerce_order_item_type');

    // Process to place order programmatically.
    $this->cartManager->addEntity($cart, $variation);

    $redirect = new RedirectResponse(Url::fromRoute('commerce_cart.page')
      ->toString());
    $redirect->send();
    exit;
  }

}
