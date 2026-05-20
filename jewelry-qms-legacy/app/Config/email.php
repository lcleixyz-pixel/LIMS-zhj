<?php
class EmailConfig {
    public $default = array(
        'transport' => 'Mail',
        'from' => 'qms@jewelry-lab.com',
        'charset' => 'utf-8',
        'headerCharset' => 'utf-8',
    );
    public $smtp = array(
        'transport' => 'Smtp',
        'from' => array('qms@jewelry-lab.com' => '珠宝检测实验室QMS'),
        'host' => 'localhost',
        'port' => 25,
        'timeout' => 30,
        'username' => '',
        'password' => '',
        'charset' => 'utf-8',
        'headerCharset' => 'utf-8',
    );
}
