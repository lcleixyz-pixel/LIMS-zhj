<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo Configure::read('QMS.title'); ?> - <?php echo h($companyName); ?></title>
    <?php
    echo $this->Html->css(array('bootstrap.min', 'layout', 'qms'));
    echo $this->Html->script(array('jquery.min', 'bootstrap.min'));
    echo $this->fetch('css');
    echo $this->fetch('script');
    ?>
</head>
<body>
<div id="header" class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <h4 class="qms-logo"><?php echo $this->Html->link(h($companyName), array('controller' => 'dashboards', 'action' => 'index')); ?></h4>
            <small class="text-muted">珠宝检测实验室 QMS</small>
        </div>
        <div class="col-md-6 text-center">
            <span class="label label-default">CMA/CNAS 质量管理体系</span>
        </div>
        <div class="col-md-3 text-right" id="login-info">
            <strong><?php echo h($this->Session->read('User.name')); ?></strong>
            <?php if (!empty($notificationCount)): ?>
                <span class="badge badge-danger"><?php echo $notificationCount; ?></span>
            <?php endif; ?>
            <br>
            <?php echo $this->Html->link('修改密码', array('controller' => 'users', 'action' => 'change_password'), array('class' => 'btn btn-xs btn-default')); ?>
            <?php echo $this->Html->link('退出', array('controller' => 'users', 'action' => 'logout'), array('class' => 'btn btn-xs btn-primary')); ?>
        </div>
    </div>
</div>
<?php echo $this->element('top_menu'); ?>
<div class="container main-content">
    <?php echo $this->Session->flash(); ?>
    <?php echo $this->fetch('content'); ?>
</div>
<div class="container">
    <footer class="footer text-center text-muted">
        <small>&copy; <?php echo date('Y'); ?> <?php echo Configure::read('QMS.title'); ?> v<?php echo Configure::read('QMS.version'); ?></small>
    </footer>
</div>
</body>
</html>
