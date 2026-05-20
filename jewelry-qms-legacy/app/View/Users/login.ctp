<?php echo $this->Form->create('User', array('class' => 'form-horizontal')); ?>
    <div class="form-group">
        <label>用户名</label>
        <?php echo $this->Form->input('username', array('label' => false, 'class' => 'form-control', 'div' => false, 'placeholder' => '请输入用户名')); ?>
    </div>
    <div class="form-group">
        <label>密码</label>
        <?php echo $this->Form->input('password', array('type' => 'password', 'label' => false, 'class' => 'form-control', 'div' => false, 'placeholder' => '请输入密码')); ?>
    </div>
    <div class="form-group">
        <?php echo $this->Form->submit('登录', array('class' => 'btn btn-primary btn-block')); ?>
    </div>
    <p class="text-muted text-center"><small>默认账号 admin / password</small></p>
<?php echo $this->Form->end(); ?>
