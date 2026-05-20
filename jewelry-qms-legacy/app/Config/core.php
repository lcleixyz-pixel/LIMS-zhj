<?php
Configure::write('debug', 0);

Configure::write('Error', array(
    'handler' => 'ErrorHandler::handleError',
    'level' => E_ALL & ~E_DEPRECATED,
    'trace' => true
));

Configure::write('Exception', array(
    'handler' => 'ErrorHandler::handleException',
    'renderer' => 'ExceptionRenderer',
    'log' => true
));

Configure::write('App.encoding', 'UTF-8');
Configure::write('Routing.prefixes', array('admin'));
Configure::write('Cache.disable', false);

Configure::write('Session', array(
    'defaults' => 'php',
    'timeout' => 120,
    'Session.checkAgent' => false
));

Configure::write('Security.salt', 'Jw3Ql9Xk7Rm2Vp8Ys5Tn4Bh6Df0Gc1Za');
Configure::write('Security.cipherSeed', '38592064710384756290146528374');

Configure::write('Acl.classname', 'DbAcl');
Configure::write('Acl.database', 'default');

date_default_timezone_set('Asia/Shanghai');

$engine = 'File';
$duration = '+999 days';
if (Configure::read('debug') > 0) {
    $duration = '+10 seconds';
}
$prefix = 'jqms_';

Cache::config('_cake_core_', array(
    'engine' => $engine,
    'prefix' => $prefix . 'cake_core_',
    'path' => CACHE . 'persistent' . DS,
    'serialize' => ($engine === 'File'),
    'duration' => $duration
));

Cache::config('_cake_model_', array(
    'engine' => $engine,
    'prefix' => $prefix . 'cake_model_',
    'path' => CACHE . 'models' . DS,
    'serialize' => ($engine === 'File'),
    'duration' => $duration
));

Configure::write('QMS.title', '珠宝检测实验室质量管理系统');
Configure::write('QMS.version', '1.0.0');
Configure::write('QMS.docLevels', array(
    '1' => '质量手册',
    '2' => '程序文件',
    '3' => '作业指导书',
    '4' => '记录表格'
));
Configure::write('QMS.approvalRules', array(
    '1' => 3,
    '2' => 3,
    '3' => 2,
    '4' => 2
));
