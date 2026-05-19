
	<?php $block_class = '';?>
	<td><?php echo  $this->Form->input('ChdMrm0Child1V1.'.$i.'.audit_number',array('label'=>false,'class'=>'form-control ChdMrm0Child1V1_AuditNumber',$block_class ,'required' , 'default'=>$this->request->data['ChdMrm0Child1V1']['audit_number'])) ;?></td>
	<?php $block_class = '';?>
	<td><?php echo  $this->Form->input('ChdMrm0Child1V1.'.$i.'.agenda_details',array('label'=>false,'class'=>'form-control ChdMrm0Child1V1_AgendaDetail',$block_class ,'default'=>$this->request->data['ChdMrm0Child1V1']['agenda_details'])) ;?></td>
	<?php $block_class = '';?>
		<td><?php echo  $this->Form->input('ChdMrm0Child1V1.'.$i.'.assigned_to',array('label'=>false,'class'=>'form-control select',$block_class , 'default'=>$this->request->data['ChdMrm0Child1V1']['assigned_to'], 'onblur'=>'donothing()')) ;?></td>
	<?php $block_class = '';?>
                                <?php if($this->request->data['ChdMrm0Child1V1']['prepared_by'] == $this->Session->read('User.employee_id')   && $this->action == 'edit' ){

                                }else{
                                    if($this->action == 'edit' )$block_class = 'readonly';
                                }?>
	<td><?php echo  $this->Form->input('ChdMrm0Child1V1.'.$i.'.target_date',array('label'=>false,'class'=>'form-control',$block_class ,'required' , 'type'=>'date','default'=>$this->request->data['ChdMrm0Child1V1']['target_date'])) ;?></td>
	<?php $block_class = '';?>
	<?php if($this->request->data['ChdMrm0Child1V1']['id'] == '')$block_class = 'readonly';?>
	<td><?php echo  $this->Form->input('ChdMrm0Child1V1.'.$i.'.closure_comments',array('label'=>false,'class'=>'form-control ChdMrm0Child1V1_ClosureComment',$block_class ,'default'=>$this->request->data['ChdMrm0Child1V1']['closure_comments'])) ;?></td>
	<?php $block_class = '';?>
	<?php if($this->request->data['ChdMrm0Child1V1']['id'] == '')$block_class = 'readonly';?>
	<td><?php echo  $this->Form->input('ChdMrm0Child1V1.'.$i.'.current_status',array('type'=>'radio', 'options' => $customArray['currentStatuses'], 'label'=>false, 'legend'=>false, 'div'=>false, 'class'=>'',$block_class ,'default'=>$this->request->data['ChdMrm0Child1V1']['current_status'])) ;?></td>
<td class='text-right'>
		<?php echo $this->Js->link('<i class="fa fa-minus btn-remove-record"></i>',array('action'=>'delete',$this->request->data['ChdMrm0Child1V1']['id'],'ChdMrm0Child1V1'),array('confirm'=>'Are you sure you want to delete this record?','class'=>'btn btn-xs btn-default','escape'=>false));?>
		<?php echo $this->Js->writeBuffer();?>

 <?php echo $this->Form->hidden('ChdMrm0Child1V1.'.$i.'.id',array('default'=> $this->request->data['ChdMrm0Child1V1']['id'])) ?>


 <?php echo $this->Form->hidden('ChdMrm0Child1V1.'.$i.'.custom_table_id',array('value'=> $this->request->data['ChdMrm0Child1V1']['custom_table_id'])) ?>

</td>