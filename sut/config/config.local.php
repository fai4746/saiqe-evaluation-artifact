<?php
/**
 * SAIQE evaluation harness override.
 *
 * Points the FCCSMS system-under-test at the isolated evaluation database
 * so that measurement runs never touch the classroom instance (`fccsms`).
 * Created by the harness; see harness/bin/reset_db.php.
 */
return [
    'base_url' => 'http://localhost/saiqe/sut',
    'db_host'  => '127.0.0.1',
    'db_port'  => 3306,
    'db_name'  => getenv('SAIQE_DB') ?: 'fccsms_saiqe',
    'db_user'  => 'root',
    'db_pass'  => '',
];
