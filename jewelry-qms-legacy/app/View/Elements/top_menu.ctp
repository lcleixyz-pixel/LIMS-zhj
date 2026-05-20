<nav class="navbar navbar-default qms-nav">
    <div class="container-fluid">
        <div class="navbar-header">
            <?php echo $this->Html->link('工作台', array('controller' => 'dashboards', 'action' => 'index'), array('class' => 'navbar-brand')); ?>
        </div>
        <div class="collapse navbar-collapse">
            <ul class="nav navbar-nav">
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown">文件控制 <b class="caret"></b></a>
                    <ul class="dropdown-menu">
                        <li><?php echo $this->Html->link('体系文件列表', array('controller' => 'documents', 'action' => 'index')); ?></li>
                        <li><?php echo $this->Html->link('新建文件', array('controller' => 'documents', 'action' => 'add')); ?></li>
                        <li class="divider"></li>
                        <li><?php echo $this->Html->link('文件模板管理', array('controller' => 'doc_templates', 'action' => 'index')); ?></li>
                        <li><?php echo $this->Html->link('文件分类', array('controller' => 'doc_categories', 'action' => 'index')); ?></li>
                    </ul>
                </li>
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown">审核与评审 <b class="caret"></b></a>
                    <ul class="dropdown-menu">
                        <li><?php echo $this->Html->link('内审计划', array('controller' => 'audit_plans', 'action' => 'index')); ?></li>
                        <li><?php echo $this->Html->link('审核发现', array('controller' => 'audit_findings', 'action' => 'index')); ?></li>
                        <li><?php echo $this->Html->link('管理评审', array('controller' => 'management_reviews', 'action' => 'index')); ?></li>
                    </ul>
                </li>
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown">质量改进 <b class="caret"></b></a>
                    <ul class="dropdown-menu">
                        <li><?php echo $this->Html->link('CAPA', array('controller' => 'capas', 'action' => 'index')); ?></li>
                        <li><?php echo $this->Html->link('不符合工作', array('controller' => 'nonconformities', 'action' => 'index')); ?></li>
                        <li><?php echo $this->Html->link('客户投诉', array('controller' => 'customer_complaints', 'action' => 'index')); ?></li>
                    </ul>
                </li>
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown">资源管理 <b class="caret"></b></a>
                    <ul class="dropdown-menu">
                        <li><?php echo $this->Html->link('设备台账', array('controller' => 'equipments', 'action' => 'index')); ?></li>
                        <li><?php echo $this->Html->link('校准记录', array('controller' => 'calibrations', 'action' => 'index')); ?></li>
                        <li><?php echo $this->Html->link('培训记录', array('controller' => 'trainings', 'action' => 'index')); ?></li>
                        <li><?php echo $this->Html->link('能力确认', array('controller' => 'competency_records', 'action' => 'index')); ?></li>
                        <li><?php echo $this->Html->link('供应商管理', array('controller' => 'suppliers', 'action' => 'index')); ?></li>
                    </ul>
                </li>
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown">系统设置 <b class="caret"></b></a>
                    <ul class="dropdown-menu">
                        <li><?php echo $this->Html->link('部门', array('controller' => 'departments', 'action' => 'index')); ?></li>
                        <li><?php echo $this->Html->link('员工', array('controller' => 'employees', 'action' => 'index')); ?></li>
                        <li><?php echo $this->Html->link('用户', array('controller' => 'users', 'action' => 'index')); ?></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
