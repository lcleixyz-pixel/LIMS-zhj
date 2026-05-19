<?php if($chdMrm0Child1V1s){ ?>
	<div class="table-responsive" style="overflow:scroll">
		<div class="panel panel-default no-margin"><div class="panel-body no-padding">
			<table class="table table-bordered table table-striped table-hover">
				<tr>
					<th><?php echo __('Audit Number'); ?></th>
					<th><?php echo __('Agenda Details'); ?></th>
					<th><?php echo __('Assigned To'); ?></th>
					<th><?php echo __('Target Date'); ?></th>
					<th><?php echo __('Closure Comments'); ?></th>
					<th><?php echo __('Current Status'); ?></th>
				</tr>
			<?php foreach ($chdMrm0Child1V1s as $chdMrm0Child1V1): ?>
				<tr>
					<td><?php echo $chdMrm0Child1V1['ChdMrm0Child1V1']['audit_number'];?></td>
					<td><?php echo $chdMrm0Child1V1['ChdMrm0Child1V1']['agenda_details'];?></td>
				<td>
				<?php 
				$img = WWW_ROOT. DS. 'img'. DS . $this->Session->read('User.company_id'). DS .'signature'. DS. $chdMrm0Child1V1['AssignedTo']["id"] . DS. 'sign.png';

                                
				if(file_exists($img)){
                                    
				$imgUrl = Router::url('/', true).'/img/' . $this->Session->read('User.company_id') .'/signature/'. $chdMrm0Child1V1['AssignedTo']["id"] . '/sign.png';
                                    
				echo '<img src="'.$imgUrl.'" width="100">';
                                
				}else if($chdMrm0Child1V1['AssignedTo']['signature']){
                                    
				echo '<img src="'.$chdMrm0Child1V1['AssignedTo']['signature'].'" width="100">';
                                }else{ echo '<small style="color:#810000">Signature not found</small>';} 
				?>
				<br /><?php echo $chdMrm0Child1V1['AssignedTo'][''];?>
				<?php
                        $default = $this->requestAction(array('action'=>'get_default','AssignedTo'));
                         echo $chdMrm0Child1V1['AssignedTo'][$default];?>
				</td>
					<td><?php 
                        if($chdMrm0Child1V1['ChdMrm0Child1V1']['target_date'])echo  date(Configure::read('dateFormat'),strtotime($chdMrm0Child1V1['ChdMrm0Child1V1']['target_date'])) ;
                        else echo "--";
                        ?></td>
					<td><?php echo $chdMrm0Child1V1['ChdMrm0Child1V1']['closure_comments'];?></td>
					<td><?php echo $customArray['currentStatuses'][$chdMrm0Child1V1['ChdMrm0Child1V1']['current_status']];?></td>
				</tr>
			<?php endforeach; ?>
		</table>
	</div></div></div>
	</div>
<?php } ?>