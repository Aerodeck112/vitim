<?php
if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}
delete_option('vitim_connector');
delete_option('vitim_connector_queue');
delete_option('vitim_connector_events');
delete_option('vitim_connector_products');
delete_option('vitim_connector_shop_sync');
