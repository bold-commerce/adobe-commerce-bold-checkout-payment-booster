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
    'opensearch-host' => '127.0.0.1',
    'opensearch-port' => 9200,
    'admin-user' => 'user',
    'admin-password' => 'password1',
    'admin-email' => 'admin@example.com',
    'admin-firstname' => 'firstname',
    'admin-lastname' => 'lastname',
    /*'amqp-host' => 'rabbitmq',
    'amqp-port' => '5672',
    'amqp-user' => 'guest',
    'amqp-password' => 'guest',*/
    // 'session-save' => 'redis',
    // 'session-save-redis-host' => '127.0.0.1',
    // 'session-save-redis-port' => 6379,
    // 'session-save-redis-db' => 2,
    // 'session-save-redis-max-concurrency' => 20,
    // 'cache-backend' => 'redis',
    // 'cache-backend-redis-server' => '127.0.0.1',
    // 'cache-backend-redis-db' => 0,
    // 'cache-backend-redis-port' => 6379,
    // 'page-cache' => 'redis',
    // 'page-cache-redis-server' => '127.0.0.1',
    // 'page-cache-redis-db' => 1,
    // 'page-cache-redis-port' => 6379,
    // 'consumers-wait-for-messages' => '0',
];