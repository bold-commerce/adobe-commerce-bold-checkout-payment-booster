<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

 use Magento\TestFramework\Bootstrap;


 return [
    'db-host' => '127.0.0.1',
    'db-user' => 'root',
    'db-password' => 'root',
    'db-name' => 'magento_integration_tests',
    'db-prefix' => '',
    'backend-frontname' => 'backend',
    'search-engine' => 'elasticsearch7',
    'admin-user' => Bootstrap::ADMIN_NAME,
    'admin-password' => Bootstrap::ADMIN_PASSWORD,
    'admin-email' => Bootstrap::ADMIN_EMAIL,
    'admin-firstname' => Bootstrap::ADMIN_FIRSTNAME,
    'admin-lastname' => Bootstrap::ADMIN_LASTNAME,
    'amqp-host' => '127.0.0.1',
    'amqp-port' => '5672',
    'amqp-user' => 'guest',
    'amqp-password' => 'guest',
    'session-save' => 'redis',
    'session-save-redis-host' => '127.0.0.1',
    'session-save-redis-port' => 6379,
    'session-save-redis-db' => 2,
    'session-save-redis-max-concurrency' => 20
];
