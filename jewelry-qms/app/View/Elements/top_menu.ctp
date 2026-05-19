<nav class="navbar navbar-default qms-nav">
    <div class="container-fluid">
        <div class="navbar-header">
            <?php echo $this->Html->link('宸ヤ綔鍙?, array('controller' => 'dashboards', 'action' => 'index'), array('class' => 'navbar-brand')); ?>
        </div>
        <div class="collapse navbar-collapse">
            <ul class="nav navbar-nav">
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown">鏂囦欢鎺у埗 <b class="caret"></b></a>
                    <ul class="dropdown-menu">
                        <li><?php echo $this->Html->link('浣撶郴鏂囦欢鍒楄〃', array('controller' => 'documents', 'action' => 'index')); ?></li>
                        <li><?php echo $this->Html->link('鏂板缓鏂囦欢', array('controller' => 'documents', 'action' => 'add')); ?></li>
                        <li class="divider"></li>
                        <li><?php echo $this->Html->link('鏂囦欢妯℃澘绠＄悊', array('controller' => 'doc_templates', 'action' => 'index')); ?></li>
                        <li><?php echo $this->Html->link('鏂囦欢鍒嗙被', array('controller' => 'doc_categories', 'action' => 'index')); ?></li>
                    </ul>
                </li>
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown">瀹℃牳涓庤瘎瀹?<b class="caret"></b></a>
                    <ul class="dropdown-menu">
                        <li><?php echo $this->Html->link('鍐呭璁″垝', array('controller' => 'audit_plans', 'action' => 'index')); ?></li>
                        <li><?php echo $this->Html->link('瀹℃牳鍙戠幇', array('controller' => 'audit_findings', 'action' => 'index')); ?></li>
                        <li><?php echo $this->Html->link('绠＄悊璇勫', array('controller' => 'management_reviews', 'action' => 'index')); ?></li>
                    </ul>
                </li>
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown">璐ㄩ噺鏀硅繘 <b class="caret"></b></a>
                    <ul class="dropdown-menu">
                        <li><?php echo $this->Html->link('CAPA', array('controller' => 'capas', 'action' => 'index')); ?></li>
                        <li><?php echo $this->Html->link('涓嶇鍚堝伐浣?, array('controller' => 'nonconformities', 'action' => 'index')); ?></li>
                        <li><?php echo $this->Html->link('瀹㈡埛鎶曡瘔', array('controller' => 'customer_complaints', 'action' => 'index')); ?></li>
                    </ul>
                </li>
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown">璧勬簮绠＄悊 <b class="caret"></b></a>
                    <ul class="dropdown-menu">
                        <li><?php echo $this->Html->link('璁惧鍙拌处', array('controller' => 'equipments', 'action' => 'index')); ?></li>
                        <li><?php echo $this->Html->link('鏍″噯璁板綍', array('controller' => 'calibrations', 'action' => 'index')); ?></li>
                        <li><?php echo $this->Html->link('鍩硅璁板綍', array('controller' => 'trainings', 'action' => 'index')); ?></li>
                        <li><?php echo $this->Html->link('鑳藉姏纭', array('controller' => 'competency_records', 'action' => 'index')); ?></li>
                        <li><?php echo $this->Html->link('渚涘簲鍟嗙鐞?, array('controller' => 'suppliers', 'action' => 'index')); ?></li>
                    </ul>
                </li>
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown">绯荤粺璁剧疆 <b class="caret"></b></a>
                    <ul class="dropdown-menu">
                        <li><?php echo $this->Html->link('閮ㄩ棬', array('controller' => 'departments', 'action' => 'index')); ?></li>
                        <li><?php echo $this->Html->link('鍛樺伐', array('controller' => 'employees', 'action' => 'index')); ?></li>
                        <li><?php echo $this->Html->link('鐢ㄦ埛', array('controller' => 'users', 'action' => 'index')); ?></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>


