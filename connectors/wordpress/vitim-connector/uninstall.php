<?php
if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}
delete_option('vitim_connector');
delete_option('vitim_connector_queue');
