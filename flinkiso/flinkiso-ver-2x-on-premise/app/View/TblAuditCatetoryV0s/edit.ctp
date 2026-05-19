
<?php echo $this->Html->script(array('jquery.validate.min', 'jquery-form.min')); ?>
<?php echo $this->fetch('script'); ?>
	<div id="TblAuditCatetoryV0_div">	
	<?php echo $this->Session->flash();?>	
		<div class="TblAuditCatetoryV0">
			<?php echo $this->element('nav-header-lists',array('postData'=>array('friendlyName'=>'Audit Catetory','pluralHumanName'=>'Tbl Audit Catetory V0s','modelClass'=>'TblAuditCatetoryV0','options'=>array(),'pluralVar'=>'tblAuditCatetoryV0s'))); ?>

			<div class="row">
				<div class="col-md-12"></div>					
			</div>
			<?php echo $this->Form->create('TblAuditCatetoryV0',array('role'=>'form','class'=>'form')); ?>
			<div class="panel panel-default">
				<div class="panel-body">
					<div class="row">
						<?php echo "<div class='col-md-12'>". $this->Form->input('name',array('class'=>'form-control')) . "</div>";?>
					<?php
							echo $this->Form->input('id');
							echo $this->Form->hidden('History.pre_post_values', array('value'=>json_encode($this->data)));
							echo $this->Form->input('branchid', array('type' => 'hidden', 'value' => $this->Session->read('User.branch_id')));
							echo $this->Form->input('departmentid', array('type' => 'hidden', 'value' => $this->Session->read('User.department_id')));						
						?>
					</div>
				</div>
			</div>

	<div class="">
		<?php echo $this->element('approval_form',array('approval'=>$approval));?>
		<?php echo $this->element('approval_history',array('approval'=>$approval,'approvals'=>$approvals,'current_approval'=>$this->request->params['named']['approval_id'],'approvalComments',$approvalComments));?>
							<?php echo $this->Form->submit(__('Submit'), array('div' => false, 'class' => 'btn btn-primary btn-success','id'=>'submit_id')); ?>
							<?php echo $this->Html->image('indicator.gif', array('id' => 'submit-indicator')); ?>
							<?php echo $this->Form->end(); ?>
							<?php echo $this->Js->writeBuffer();?>						
					</div>
				</div>
		</div>
<script> 
    $.validator.setDefaults({
    	ignore: null,
    	errorPlacement: function(error, element) {
	    	if(element['context']['className'] == 'form-control select error'){
				$(element).next().after(error); 		
			}else{
				$(element).after(error); 
			}
		}
    });
    
    $().ready(function() {
    	$('select').chosen();

    	jQuery.validator.addMethod("greaterThanZero", function(value, element) {
            return this.optional(element) || (parseFloat(value) != -1);
        }, "Please select the value");

        $('#TblAuditCatetoryV0EditForm').validate();

        $('select').each(function() {	
    		if($(this).prop('required') == true){
    			$(this).rules('add', {
		        	greaterThanZero: true
		    	});	
    		}
			
		});        
			
        $("#submit-indicator").hide();
        $("#submit_id").click(function(){
            if($('#TblAuditCatetoryV0EditForm').valid()){
				$("#submit_id").prop("disabled",true);
				$("#submit-indicator").show();
				$('#TblAuditCatetoryV0EditForm').submit();
            }
        });
	});
</script>
