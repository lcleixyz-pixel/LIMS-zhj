
<?php echo $this->Html->script(array('jquery.validate.min', 'jquery-form.min')); ?>
<?php echo $this->fetch('script'); ?>
	<div class="custom_form" id="TblAuditSchedule0V0_div">	
	<?php echo $this->Session->flash();?>	
		<div class="TblAuditSchedule0V0">
			<?php 
			if($this->request->is('ajax') == false){
			echo $this->element('nav-header-lists',array('postData'=>array('friendlyName'=>'Audit Schedule','pluralHumanName'=>'Tbl Audit Schedule 0 V0s','modelClass'=>'TblAuditSchedule0V0','options'=>array(),'pluralVar'=>'tblAuditSchedule0V0s'))); 
			}?>
<?php if($this->action == 'edit'){ ?>
	<div id="load_parent">
	<?php if($this->request->data['TblAuditSchedule0V0']['parent_id'] && isset($parent_table_name)){ ?>
	<script type="text/javascript">
		$().ready(function(){
			$.ajax({
				url: "<?php echo Router::url('/', true); ?><?php echo $parent_table_name;?>/view/<?php echo $this->request->data['TblAuditSchedule0V0']['parent_id'];?>",
				type : "POST",
				data : {															
					'Access[skip_access_check]': 1,								
					'Access[allow_access_user]': '<?php echo $this->Session->read('User.id');?>',
					},
		        success: function(data, result) {					        	
		        	$("#load_parent").html($(data).find("\#ajax_view").html());
		        },						        
			});
		});
	</script>
	<?php }?>
	</div>
<?php } ?>	
<div class="row">
				<div class="col-md-12"><?php echo $this->element('qc_doc_header',array('document',$document));?></div>					
			</div>

<div class="row">
	<div class="col-md-12">
		<?php		
			$key = $fileEdit['File']['file_key'];
			$file_type = $fileEdit['File']['file_type'];
	        $file_name = $fileEdit['File']['name'];
	        
	        if($file_type == 'doc' || $file_type == 'docx'){
	            $documentType = 'word';
	        }

	        if($file_type == 'xls' || $file_type == 'xlsx'){
	            $documentType = 'cell';
	        }
	        $mode = 'edit';
	        $file_path = $fileEdit['File']['id'];
	        $file_name = $this->requestAction(array('action'=>'clean_table_names',$file_name));
	        $file = $file_name.'.'.$file_type;
	        
			echo $this->element('onlyoffice',array(
	            'url'=>$url,
	            'user_id'=>$user_id,
	            'placeholderid'=>$placeholderid,
	            'panel_title'=>'Document Viewer',
	            'mode'=>$mode,
	            'path'=>$file_path,
	            'file'=>$file,
	            'filetype'=>$file_type,
	            'documentType'=>$documentType,
	            'userid'=>$this->Session->read('User.id'),
	            'username'=>$this->Session->read('User.username'),
	            'preparedby'=>$this->Session->read('User.name'),
	            'filekey'=>$key,
	            'record_id'=>$fileEdit['File']['id'],
	            'company_id'=>$this->Session->read('User.company_id'),
	            'controller'=>$this->request->controller,
	            'last_saved' => $fileEdit['File']['last_saved'],
            	'last_modified' => $fileEdit['File']['modified'],
            	'version_keys' => $fileEdit['File']['version_keys'],
            	'version' => $this->request->data['QcDocument']['version'],
				'versions' => $this->request->data['QcDocument']['versions'],
				'docid'=> $this->request->data['QcDocument']['id']
	        ));
		?>
	</div>
</div>

			<?php echo $this->Form->create('TblAuditSchedule0V0',array('role'=>'form','class'=>'form','type'=>'file')); ?>
			<div class="panel panel-default">
				<div class="panel-body">
					<div class="row">						<?php $block_class = '';?>
						<?php echo "<div class='col-md-4'>". $this->Form->input('audit_number',array( 'label' => 'Audit Number', 'default'=>'', 'placeholder'=>$lastval, 'class'=>'form-control', $block_class ,'required' ,  'onblur'=>'checkunique(btoa(this.value),"audit_number",this.id)')) . "</div>";?>
						<?php $block_class = '';?>
						<?php echo "<div class='col-md-4'>". $this->Form->input('standard',array( 'label' => 'Standard', 'class'=>'form-control select Standard ' , $block_class , 'required' ,  'showdocs'=> 0 ,'showdocs_mode'=>0 , 'showdocs_copy'=> 0 , 'model'=>'Standard', 'onblur'=>'donothing()')) . "</div>";?>
						<?php $block_class = '';?>
						<?php echo "<div class='col-md-4'>". $this->Form->input('audit_category',array( 'label' => 'Audit Category', 'class'=>'form-control select AuditCategory ' , $block_class , 'required' ,  'showdocs'=> 0 ,'showdocs_mode'=>0 , 'showdocs_copy'=> 0 , 'model'=>'AuditCategory', 'onblur'=>'donothing()')) . "</div>";?>
						<?php $block_class = '';?>
						<?php echo "<div class='col-md-6'>". $this->Form->input('schedule_start_date',array( 'label' => 'Schedule Start Date', 'class'=>'form-control', $block_class , 'required' , 'type'=>'date', 'onblur'=>'donothing()')) . "</div>";?>
						<?php $block_class = '';?>
						<?php echo "<div class='col-md-6'>". $this->Form->input('scheduled_end_date',array( 'label' => 'Scheduled End Date', 'class'=>'form-control', $block_class , 'required' , 'type'=>'date', 'onblur'=>'donothing()')) . "</div>";?>
						<?php $block_class = '';?>
						<?php echo "<div class='col-md-12'>". $this->Form->input('audit_locations',array(
									'name'=>'data[TblAuditSchedule0V0][audit_locations][]',
									'type'=>'select',
									$block_class ,
									'selected'=>json_decode($this->request->data['TblAuditSchedule0V0']['audit_locations']),
									'options'=> $branches , 'class'=>'form-control select','multiple','required' ,  'onblur'=>'donothing()')) . "</div>";?>
						<?php $block_class = '';?>
						<?php echo "<div class='col-md-12'>". $this->Form->input('departments_to_be_audited',array(
									'name'=>'data[TblAuditSchedule0V0][departments_to_be_audited][]',
									'type'=>'select',
									$block_class ,
									'selected'=>json_decode($this->request->data['TblAuditSchedule0V0']['departments_to_be_audited']),
									'options'=> $departments , 'class'=>'form-control select','multiple','required' ,  'onblur'=>'donothing()')) . "</div>";?>
						<?php $block_class = '';?>
						<?php echo "<div class='col-md-12'>". $this->Form->input('current_status',array('type'=>'radio', 'options' => $customArray['currentStatuses'],  'legend' => 'Current Status', 'class'=>'', $block_class , 'required' ,  'onblur'=>'donothing()')) . "</div>";?>
						<?php $block_class = '';?>
						<?php echo "<div class='col-md-12'>". $this->Form->input('notes',array( 'label' => 'Notes', 'default'=>'', 'class'=>'form-control', $block_class ,'required' ,  'onblur'=>'donothing()')) . "</div>";?>
					<?php
						echo $this->Form->input('id');
						echo $this->Form->hidden('qc_document_id',array('default'=>$this->request->params['named']['qc_document_id']));
						echo $this->Form->hidden('data_type',array('default'=>$document['QcDocument']['data_type']));
						echo $this->Form->hidden('file_id',array('default'=>$fileEdit['File']['id']));
						echo $this->Form->hidden('file_key',array('default'=>$fileEdit['File']['file_key']));
						echo $this->Form->hidden('History.pre_post_values', array('value'=>json_encode($this->request->data)));
						echo $this->Form->input('branchid', array('type' => 'hidden', 'value' => $this->Session->read('User.branch_id')));
						echo $this->Form->input('departmentid', array('type' => 'hidden', 'value' => $this->Session->read('User.department_id')));						
					?>
					</div>
				</div>
			</div>
				
<?php  
	if($linkedTables){		
		$i = 0; foreach($linkedTables as $linkedTable){ ?>
			<div><h4><?php echo Inflector::humanize($linkedTable['CustomTable']['name']);?> <small><?php echo $linkedTable['CustomTable']['name'];?></small></h4></div>	
			<?php 
			if($this->request->data[Inflector::classify($linkedTable['CustomTable']['table_name'])]){
				if($linkedTable['CustomTable']['form_layout'] == 2){
					echo "<div class='panel panel-default'><div class='panel-body no-padding'>";
					echo "<div class='table-responsive'><table class='table table-responsive table-bordered'><tr>";
					foreach(json_decode($linkedTable['CustomTable']['fields'],true) as $field){
						if($field['display_type'] != 8){
							if($field['field_label']) echo "<th>" . base64_decode(Inflector::humanize($field['field_label'])) . "</th>";
							else echo "<th>" . Inflector::humanize($field['field_name']) . "</th>";	
						}
						
					}
					echo "</tr>";
				}
				foreach($this->request->data[Inflector::classify($linkedTable['CustomTable']['table_name'])] as $cdata){ 
					if($linkedTable['CustomTable']['form_layout'] == 2){				
						echo "<tr id='".$cdata['id']."'></tr>";
					}else{
						echo "<div class='panel panel-default'><div class='panel-body'>";
						echo "<div id='".$cdata['id']."'></div>";
						echo "</div></div>";
					}
				?>
				<script type="text/javascript">
				$("#<?php echo $cdata['id']?>").load("<?php echo Router::url('/', true); ?><?php echo $linkedTable['CustomTable']['table_name']?>/edit/<?php echo $cdata['id'];?>/<?php echo $i;?>/custom_table_id:<?php echo $linkedTable['CustomTable']['id'];?>",function(response,status,xhr){
						if(status == 'success'){
							$('select').chosen();
						}
					});
				</script>
			<?php $i++;
				}
				if($linkedTable['CustomTable']['form_layout'] == 2){ 
					echo "</table></div></div></div>";			
				}		
			?>
			<div class="panel panel-default">
				<div class="panel-body">
					<div id="<?php echo $linkedTable['CustomTable']['table_name']?>"></div>
				</div>
			</div>
			<?php if($linkedTable['CustomTable']['form_layout'] == 1){
				echo "</div></div>";
			}?>
			
			
			<script type="text/javascript">
				$("#<?php echo $linkedTable['CustomTable']['table_name']?>").load("<?php echo Router::url('/', true); ?><?php echo $linkedTable['CustomTable']['table_name']?>/add/<?php echo $i;?>/custom_table_id:<?php echo $linkedTable['CustomTable']['id'];?>");
			</script>
		<?php } else{ ?>
			<div id="<?php echo $linkedTable['CustomTable']['table_name']?>"></div>
			<script type="text/javascript">
				$("#<?php echo $linkedTable['CustomTable']['table_name']?>").load("<?php echo Router::url('/', true); ?><?php echo $linkedTable['CustomTable']['table_name']?>/add/<?php echo $i;?>/custom_table_id:<?php echo $linkedTable['CustomTable']['id'];?>");
			</script>
		<?php	}}}else{ ?>
		<?php echo "</div></div>";}?>


	<div class="row">
		<div class="col-md-12">
		<?php echo $this->element('approval_history',array('approval'=>$approval,'approvals'=>$approvals,'current_approval'=>$this->request->params['named']['approval_id'],'approvalComments',$approvalComments));?>
		</div>
		<div class="col-md-12">
			<?php echo $this->element('approval_form',array('approval'=>$approval));?>
		</div>		
		
<div class="row">
	<?php if(($this->Session->read('User.is_mr') == 1 || $this->Session->read('User.is_approver') == 1 || $this->Session->read('User.is_hod') == 1)){ ?>
	
	<script type="text/javascript">
		function addappedit(){						
			if ($('#<?php echo Inflector::Classify($this->request->controller);?>Publish').is(':checked') == true) {
				$("#<?php echo Inflector::Classify($this->request->controller);?>ApprovedBy").val("<?php echo $this->Session->read('User.employee_id')?>").trigger('chosen:updated');
			}else{		
				$("#<?php echo Inflector::Classify($this->request->controller);?>ApprovedBy").val("-1").trigger('chosen:updated');
			}
		}
		function approveedit(val){						
			if (val == 1) {
				$('#<?php echo Inflector::Classify($this->request->controller);?>Publish').prop('checked',true);
				$("#<?php echo Inflector::Classify($this->request->controller);?>ApprovedBy").val("<?php echo $this->Session->read('User.employee_id')?>").trigger('chosen:updated');
			}else{		
				$('#<?php echo Inflector::Classify($this->request->controller);?>Publish').prop('checked',false);
				$("#<?php echo Inflector::Classify($this->request->controller);?>ApprovedBy").val("-1").trigger('chosen:updated');
			}
		}
</script>
<?php }?>
</div>

	        <div class='col-md-12'>
				<?php echo $this->Form->submit(__('Submit'), array('div' => false, 'class' => 'btn btn-primary btn-success','id'=>'submit_id')); ?>
				<?php echo $this->Html->image('indicator.gif', array('id' => 'submit-indicator')); ?>
			</div>
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
			$(element).next('.chosen-container').addClass('error');
		}else if(element.attr("fieldset") != ''){						
			$(element).parent('fieldset').addClass('error-radio');
		}else{			
			$(element).after(error); 
		}
	},
});
    
$().ready(function() {
	$('select').chosen();

	$('input').each(function(){
		var id = this.id;		
		$("#"+id).on('change',function(){			
			$("#"+id).removeClass('error').addClass('success');
			$("#"+id).next('.chosen-container').removeClass('error').addClass('success');
			$("#"+id).parent('fieldset').removeClass('error-radio').addClass('success-radio');
		});
	});

	$('select').each(function(){
		var id = this.id;		
		$("#"+id).on('change',function(){			
			$("#"+id).next('.chosen-container').removeClass('error').addClass('success');			
		});
	});


	jQuery.validator.addMethod("greaterThanZero", function(value, element) {
        return this.optional(element) || $("#"+element.id+" option:selected").text() != 'Select';
    }, "Please select the value");

    jQuery.validator.addMethod("greaterThanZeroRadio", function(value, element) {
        return $("input[name='"+element.name+"']").is(':checked') != false;
    }, "Please select the value");

	$('#TblAuditSchedule0V0AddForm').validate();
    $('select').each(function() {	
		if($(this).prop('required') == true){
			$(this).rules('add', {
	        	greaterThanZero: true
	    	});	
		}
	});  

	$('input[type=radio]').each(function() {	
		if($(this).prop('required') == true){
			$(this).rules('add', {
	        	greaterThanZeroRadio: true
	    	});	
		}
	});

    $("#submit-indicator").hide();
    $("#submit_id").click(function(){
        if($('#TblAuditSchedule0V0AddForm').valid()){
	$("#submit_id").prop("disabled",true);
	$("#submit-indicator").show();
	$('#TblAuditSchedule0V0AddForm').submit();
        }
    });
});
</script>
