<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title>鐧诲綍 - <?php echo Configure::read('QMS.title'); ?></title>
    <?php echo $this->Html->css(array('bootstrap.min', 'qms')); ?>
</head>
<body class="login-page">
<div class="container">
    <div class="row">
        <div class="col-md-4 col-md-offset-4 login-box">
            <h3 class="text-center"><?php echo Configure::read('QMS.title'); ?></h3>
            <p class="text-center text-muted">鐝犲疂妫€娴嬪疄楠屽璐ㄩ噺绠＄悊绯荤粺</p>
            <?php echo $this->Session->flash(); ?>
            <?php echo $this->fetch('content'); ?>
        </div>
    </div>
</div>
</body>
</html>


