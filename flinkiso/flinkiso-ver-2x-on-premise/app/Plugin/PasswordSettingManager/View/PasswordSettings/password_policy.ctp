<?php echo $this->Html->script(array('jquery.validate.min', 'jquery-form.min')); ?><?php echo $this->fetch('script'); ?>
<style>
	.chosen-container-single, .chosen-single, .chosen{ min-width:100px !important}
	b { font-weight: normal !important; color:#CCCCCC !important}
</style>
<script>

	$.validator.setDefaults({
		ignore: null,
		errorPlacement: function(error, element) {

			$(element).after(error);

		},
      //  submitHandler: function(form) {
          // $('#PasswordSettingPasswordPolicyForm').submit();

//            $(form).ajaxSubmit({
//                url: "<?php //echo Router::url('/', true); ?>password_setting_manager/password_settings/password_policy",
//                type: 'POST',
//               // target: '#password_policies_ajax',
//                beforeSend: function(){
//                   $("#submit_id").prop("disabled",true);
//                    $("#submit-indicator").show();
//                },
//                complete: function() {
//                   $("#submit_id").removeAttr("disabled");
//                   $("#submit-indicator").hide();
//                },
//                error: function(request, status, error) {
//                    //alert(request.responseText);
//                    alert('Action failed!');
//                }
//	    });
    //    }
	});

	$().ready(function() {
		$("#submit-indicator").hide();
		$("#submit_id").click(function(){
			if($('#PasswordSettingPasswordPolicyForm').valid()){
				$("#submit_id").prop("disabled",true);
				$("#submit-indicator").show();
				$("#PasswordSettingPasswordPolicyForm").submit();
			}
		});
		docChangeRequired();
  //  $("#submit-indicator").hide();
     //   $('#PasswordSettingPasswordPolicyForm').validate();


		$("#PasswordSettingActivatePasswordSetting").click(function(){
			docChangeRequired();
		});
	});
	function docChangeRequired(){

		var changeRequired = $("#PasswordSettingActivatePasswordSetting").is(':checked');
		if(changeRequired == true){
			$("#docChangeReq").show();
		}else{
			$("#docChangeReq").hide();
		}
	}
</script>
<script>
	$(function() {
		$( "#password-length" ).slider({
			range: true,
			min: 0,
			max: 20,
			values: [ <?php echo isset($PasswordSetting['password_min_len']) && $PasswordSetting['password_min_len']?$PasswordSetting['password_min_len']:3 ; ?>,  <?php echo isset($PasswordSetting['password_max_len']) && $PasswordSetting['password_min_len']?$PasswordSetting['password_max_len']:10 ; ?>],
			slide: function( event, ui ) {
				$( "#PasswordSettingPasswordMinLen" ).val(ui.values[ 0 ]);
				$( "#PasswordSettingPasswordMaxLen" ).val(ui.values[ 1 ]);
				$( "#MinLength" ).html("<b>Min:</b> "+$( "#password-length" ).slider( "values", 0 ));
				$( "#MaxLength" ).html("<b>Max:</b> "+$( "#password-length" ).slider( "values", 1 ));
			}

		});
		$( "#PasswordSettingPasswordMinLen" ).val($( "#password-length" ).slider( "values", 0 ));
		$( "#PasswordSettingPasswordMaxLen" ).val($( "#password-length" ).slider( "values", 1 ));
		$( "#MinLength" ).html("<b>Min:</b> "+$( "#password-length" ).slider( "values", 0 ));
		$( "#MaxLength" ).html("<b>Max:</b> "+$( "#password-length" ).slider( "values", 1 ));

		$( "#password-repeat" ).slider({
			range: "min",
			min: 1,
			max: 10,
			value: <?php echo (isset($PasswordSetting['password_repeat']) && $PasswordSetting['password_repeat']) ? $PasswordSetting['password_repeat']:3 ; ?>,
			slide: function( event, ui ) {
				$( "#PasswordSettingPasswordRepeat" ).val(ui.value);
				$( "#MaxPasswordRepeat" ).html("<b>Value: </b>"+ui.value);
                //$( "#amount" ).val( "Min length" + ui.values[ 0 ] + " - Max length" + ui.values[ 1 ] );
			}

		});
		$( "#PasswordSettingPasswordRepeat" ).val($( "#password-repeat" ).slider( "value" ));
		$( "#MaxPasswordRepeat" ).html("<b>Value: </b>"+$( "#password-repeat" ).slider( "value" ));

	});
</script>
<?php $options = array( 1=>"Yes",2=>"No", 0=>"N/A");
?>

<div id="password_policies_ajax"> <?php echo $this->Session->flash(); ?>
<div class="nav panel panel-default">
	<div class="password_policies form col-md-12">
		<h4><?php echo __("Add Password Policy"); ?></h4>
		<?php echo $this->Form->create('PasswordSetting', array('action'=>'password_policy'), array('role' => 'form', 'class' => 'form')); ?> 
		<?php echo $this->Form->input('id');
		$checked =  (isset($actPwdSetting) && $actPwdSetting == 1)  ? 'checked':'';
		$checkedtwo =  (isset($twoway) && $twoway == true)  ? 'checked':'';
		
		?>
		<!--            <input type="text" id="amount" readonly style="border:0; color:#f6931f; font-weight:bold;">-->
		<div class="row">
			<div class="alert text-info">You can Define / Activate password complexity policy by clicking on "Activate password policy" checkbox</div>
			<div class="col-md-6"><?php echo $this->Form->input('activate_password_setting', array('type' => 'checkbox', 'label'=>'Activate password policy',  'checked'=>$checked)); ?></div>
			<div class="col-md-6"><?php echo $this->Form->input('two_way_authentication', array('type' => 'checkbox', 'label'=>'Two Way Authentication',  'checked'=>$checkedtwo)); ?></div>
		</div>
		<div id="docChangeReq">
			<hr />
			<div class="row">
				<div class="col-md-12"> <?php echo $this->Form->input('display_policy', array('type' => 'checkbox','label' => 'Display Login Policy On Login Page') ); ?> </div>
				<div class="col-md-12"> <?php echo $this->Form->input('concurrent_login', array('type' => 'checkbox','label' => 'Allow Concurrent Login') ); ?> </div>
				<div class="col-md-12"> <?php echo $this->Form->input('password_same_username', array('type' => 'checkbox','label' => 'Password should not be same as username') ); ?> </div>
			</div>
			<div class="row">
				<hr />
				<div class="col-md-6"><label> Set Password Length</label></div>
				<div class="col-md-6">
					<div id="password-length"></div>
					<span id="MinLength"> </span> <span id="MaxLength" class="pull-right"> </span> <?php echo $this->Form->input('password_min_len', array('type' => 'hidden')); ?> <?php echo $this->Form->input('password_max_len', array('type' => 'hidden')); ?>
				</div>
			</div>
			<div class="row">
				<hr />
				<div class="col-md-6"> <label>Min number of uppercase characters allow</label> </div>
				<div class="col-md-6"> <?php
				for($i = 0; $i <= 10; $i++){
					$lenght[$i] = $i;
				}
				echo $this->Form->input('password_uppercase_length', array('label' => false, 'options' => $lenght) );
				?>
			</div>
		</div>
		<div class="row">
			<hr />
			<div class="col-md-6"><label>Password must start with Uppercase </label></div>
			<div class="col-md-6"> <?php echo $this->Form->input('password_uppercase_start', array('type' => 'radio','options' =>$options,'label' => false, 'legend' => false, 'div' => false, 'style' => 'float:none') ); ?> </div>
		</div>
		<div class="row">
			<hr />
			<div class="col-md-6"><label>Password must contain special characters </label></div>
			<div class="col-md-6"> <?php echo $this->Form->input('password_special_character', array('type' => 'radio','options' =>$options,'label' => false, 'legend' => false, 'div' => false, 'style' => 'float:none') ); ?> </div>
		</div>
		<div class="row">
			<hr />
			<div class="col-md-6"><label> "X" number of last passwords can not be repeated </label></div>
			<div class="col-md-6"><div id="password-repeat"></div>
			<?php echo $this->Form->input('password_repeat', array('type' => 'hidden') ); ?>
			<span id="MaxPasswordRepeat"> </span> </div>
		</div>
		<div class="row">
			<hr />
			<div class="col-md-6"><label>Password should be change </label> </div>
			<div class="col-md-6"><?php echo $this->Form->input('password_change_remind', array('type' => 'radio','options' =>array(1=>'Weekly',2=>'Monthly',3 =>'Quarterly', 4=>'Yearly', 0=>'N/A'),'label' => false, 'legend' => false,  'div' => false, 'style' => 'float:none') ); ?> </div>
		</div>
		<div class="row">
			<hr />
			<?php
			echo $this->Form->input('branchid', array('type' => 'hidden', 'value' => $this->Session->read('User.branch_id')));
			echo $this->Form->input('departmentid', array('type' => 'hidden', 'value' => $this->Session->read('User.department_id')));
			echo $this->Form->input('master_list_of_format_id', array('type' => 'hidden', 'value' => $documentDetails['MasterListOfFormat']['id']));
			?>
		</div>
		<?php
		if ($showApprovals && $showApprovals['show_panel'] == true) {
			echo $this->element('approval_form');
		} else {
			echo $this->Form->input('publish', array('label' => __('Publish')));
		}
		?>
	</div>
	<br/>
	<?php echo $this->Form->submit(__('Submit'), array('div' => false, 'class' => 'btn btn-primary btn-success', 'update' => '#password_policies_ajax', 'async' => 'true','id'=>'submit_id')); ?> <?php echo $this->Html->image('indicator.gif', array('id' => 'submit-indicator')); ?> <?php echo $this->Form->end(); ?> <?php echo $this->Js->writeBuffer(); ?> </div>

</div>
</div>
<script>
	$.ajaxSetup({beforeSend: function() {
		$("#busy-indicator").show();
	}, complete: function() {
		$("#busy-indicator").hide();
	}});
</script>
