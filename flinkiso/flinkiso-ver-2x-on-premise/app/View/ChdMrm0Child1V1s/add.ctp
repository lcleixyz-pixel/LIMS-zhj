
<style>
#ChdMrm0Child1V1_tbl td{padding:2px}
# #ChdMrm0Child1V1_tbl input{min-width:75px}
#ChdMrm0Child1V1_tbl select{min-width:175px}
.chosen-drop{z-index: 999}
</style>
<div class='table-responsive' style='min-height:350px'>
<table class='table custom_table table-stripped' id='ChdMrm0Child1V1_tbl'>
    <tr class='bg-gray'>	<?php echo $this->Form->hidden('ChdMrm0Child1V1.'.$i.'.custom_table_id',array('value' => $this->request->params['named']['custom_table_id'])) ;?></div>
		<th><?php echo "Audit Number";?></th>
		<th><?php echo "Agenda Details";?></th>
		<th><?php echo "Assigned To";?></th>
		<th><?php echo "Target Date";?></th>
		<th><?php echo "Closure Comments";?></th>
		<th><?php echo "Current Status";?></th>
		<th width='120'></th>
	</tr>
	<tr id="<?php echo $i;?>_tr">
	<?php $block_class = '';?>
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
</table><?php echo $this->Form->hidden('ChdMrm0Child1V1.count',array('id'=> 'ChdMrm0Child1V1count', 'default'=>$i));?><?php echo $this->Form->hidden('ChdMrm0Child1V1.file_id',array('default'=>$fileData['File']['id']));?><?php echo $this->Form->hidden('ChdMrm0Child1V1.file_key',array('default'=>$fileData['File']['file_key']));?></div>
<script type="text/javascript">
    
    $().ready(function(){
        $('select').chosen();
    });

    function ChdMrm0Child1V1_tbl_addchildrow(f){
        var f = parseInt($("#ChdMrm0Child1V1count").val());
        f = f + 1;
        $.ajax({
            url: "<?php echo Router::url('/', true); ?><?php echo $this->request->params['controller'] ?>/add_fields/"+f+"/custom_table_id:<?php echo $this->request->params['named']['custom_table_id'];?>",
            success: function(data, result) {                                   
                $("#ChdMrm0Child1V1_tbl > tbody ").append(data);
                $('select').chosen();
                $("#ChdMrm0Child1V1count").val(f);
                var rowCount = $('#ChdMrm0Child1V1_tbl tr').length;
                if(rowCount > 1){
                    $("#1stbtn").hide()
                }
            },
        }); 
    }

    function ChdMrm0Child1V1_tbl_delchildrow(f){
        $("#"+f+"_tr").remove();
        var rowCount = $('#ChdMrm0Child1V1_tbl tr').length;        
        if(rowCount == 2){
            $("#1stbtn").show()
        }        
    }
</script>
