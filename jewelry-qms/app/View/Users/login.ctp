<?php echo $this->Form->create('User', array('class' => 'form-horizontal')); ?>
    <div class="form-group">
        <label>鐢ㄦ埛鍚?/label>
        <?php echo $this->Form->input('username', array('label' => false, 'class' => 'form-control', 'div' => false, 'placeholder' => '璇疯緭鍏ョ敤鎴峰悕')); ?>
    </div>
    <div class="form-group">
        <label>瀵嗙爜</label>
        <?php echo $this->Form->input('password', array('type' => 'password', 'label' => false, 'class' => 'form-control', 'div' => false, 'placeholder' => '璇疯緭鍏ュ瘑鐮?)); ?>
    </div>
    <div class="form-group">
        <?php echo $this->Form->submit('鐧诲綍', array('class' => 'btn btn-primary btn-block')); ?>
    </div>
    <p class="text-muted text-center"><small>榛樿璐﹀彿 admin / password</small></p>
<?php echo $this->Form->end(); ?>


