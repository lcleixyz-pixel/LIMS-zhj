<tr id='<?php echo $i;?>_tr'>	<?php $block_class = '';?>
		<td><?php echo  $this->Form->input('ChdMrm0Child1V1.'.$i.'.audit_number',array('label'=>false,'class'=>'form-control ChdMrm0Child1V1_AuditNumber',$block_class ,'required' ,  'onblur'=>'donothing()')) ;?></td>
	<?php $block_class = '';?>
		<td><?php echo  $this->Form->input('ChdMrm0Child1V1.'.$i.'.agenda_details',array('label'=>false,'class'=>'form-control ChdMrm0Child1V1_AgendaDetail',$block_class , 'onblur'=>'donothing()')) ;?></td>
	<?php $block_class = '';?>
		<td><?php echo  $this->Form->input('ChdMrm0Child1V1.'.$i.'.assigned_to',array('label'=>false,'class'=>'form-control select',$block_class , 'onblur'=>'donothing()' , 'default'=>$this->request->data['ChdMrm0Child1V1']['assigned_to'])) ;?></td>
	<?php $block_class = '';?>
                                <?php if($this->request->data['ChdMrm0Child1V1']['prepared_by'] == $this->Session->read('User.employee_id')   && $this->action == 'edit' ){

                                }else{
                                    if($this->action == 'edit' )$block_class = 'readonly';
                                }?>
		<td><?php echo  $this->Form->input('ChdMrm0Child1V1.'.$i.'.target_date',array('label'=>false,'class'=>'form-control',$block_class ,'required' , 'type'=>'date', 'onblur'=>'donothing()')) ;?></td>
	<?php $block_class = '';?>
	<?php if($this->request->data['ChdMrm0Child1V1']['id'] == '')$block_class = 'readonly';?>
		<td><?php echo  $this->Form->input('ChdMrm0Child1V1.'.$i.'.closure_comments',array('label'=>false,'class'=>'form-control ChdMrm0Child1V1_ClosureComment',$block_class , 'onblur'=>'donothing()')) ;?></td>
	<?php $block_class = '';?>
	<?php if($this->request->data['ChdMrm0Child1V1']['id'] == '')$block_class = 'readonly';?>
		<td><?php echo  $this->Form->input('ChdMrm0Child1V1.'.$i.'.current_status',array('type'=>'radio', 'legend'=> false, 'div'=>false, 'options' => $customArray['currentStatuses'], 'label'=>false, 'class'=>'', $block_class , 'onblur'=>'donothing()')) ;?></td>

        <td>
            <div class="btn-group pull-right">
                <div class="btn btn-sm btn-default text-success btn-add-record" onclick="ChdMrm0Child1V1_tbl_addchildrow(<?php echo $i;?>)">+</div>
                <div class="btn btn-sm btn-default text-danger btn-remove-record" onclick="ChdMrm0Child1V1_tbl_delchildrow(<?php echo $i;?>)">-</div>
            </div>
        </td>
    	</tr> 
