<div  id="main">
<?php echo $this->Session->flash();?>
<?php echo $this->element('nav-header-lists',array('postData'=>array('friendlyName'=>'MRM','pluralHumanName'=>'tbl_mrm_0_v0s','modelClass'=>'TblMrm0V0','options'=>array("meeting_number"=>"Meeting Number","scheduled_date_time"=>"Scheduled Date Time","proposed_by"=>"Proposed By","meeting_details"=>"Meeting Details","invitees"=>"Invitees","meeting_status"=>"Meeting Status","comments5"=>"Comments5","actual_meeting_date_time"=>"Actual Meeting Date Time","attainted_by"=>"Attainted By","meeting_number"=>"Meeting Number","scheduled_date_time"=>"Scheduled Date Time","proposed_by"=>"Proposed By","meeting_details"=>"Meeting Details","invitees"=>"Invitees","meeting_status"=>"Meeting Status","comments5"=>"Comments5","actual_meeting_date_time"=>"Actual Meeting Date Time","attainted_by"=>"Attainted By"),'pluralVar'=>'tblMrm0V0s'))); ?>
<div id="load_parent">
    <?php if($tblMrm0V0['TblMrm0V0']['parent_id'] && isset($parent_table_name)){ ?>
    <script type="text/javascript">
        $().ready(function(){
            $.ajax({
                url: "<?php echo Router::url('/', true); ?><?php echo $parent_table_name;?>/view/<?php echo $tblMrm0V0['TblMrm0V0']['parent_id'];?>",
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
<div id="ajax_view">
<div class="row"><div class="col-md-12">
<?php echo $this->element('qc_doc_header',array('document',$document));?></div></div>
<div class="view-record"><h2><small>Meeting Number: </small><?php echo $tblMrm0V0['TblMrm0V0']['meeting_number'];?></h2>

<div class="row">
    <div class="col-md-12">
        <strong style="float:left"><?php echo $document['QcDocument']['name'];?> Document</strong>
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
            $mode = 'view';
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
                'version' => $fileEdit['File']['version'],
                'versions' => $fileEdit['File']['versions'],
                'docid'=> $qcDocument['QcDocument']['id']
            ));
        ?>
    </div>
</div>
<br />

	<div class="table-responsive">
		<table class="table table-bordered table-responsive" id="exportcsv"  >
			<tr>
				<th width="30%"> Meeting Number</th>
                                <td><?php echo $tblMrm0V0['TblMrm0V0']['meeting_number'];?></td>
			</tr>
			<tr>
				<th width="30%"> Scheduled Date Time</th>
                                <td><?php 
                                if($tblMrm0V0['TblMrm0V0']['scheduled_date_time'])echo  date(Configure::read('dateTimeFormat'),strtotime($tblMrm0V0['TblMrm0V0']['scheduled_date_time'])) ;
                                else echo "--"
                                ?></td>
			<tr>
				<th width="30%">Proposed By</th>
	<td><?php echo $tblMrm0V0['ProposedBy']['name'];?></td>
			</tr>
			<tr>
				<th width="30%"> Meeting Details</th>
                                <td><?php echo $tblMrm0V0['TblMrm0V0']['meeting_details'];?></td>
			</tr>
			<tr>
				<th width="30%">Invitees</th>
	<td><?php foreach(json_decode($tblMrm0V0['TblMrm0V0']['invitees'],true) as $field ){
                            echo $invitees[$field] .", ";
                        };?></td>
			</tr>
			<tr>
				<th width="30%">Meeting Status</th>
	<td><?php echo $customArray['meetingStatuses'][$tblMrm0V0['TblMrm0V0']['meeting_status']];?></td>
			</tr>
			<tr>
				<th width="30%"> Actual Meeting Date Time</th>
                                <td><?php 
                                if($tblMrm0V0['TblMrm0V0']['actual_meeting_date_time'])echo  date(Configure::read('dateTimeFormat'),strtotime($tblMrm0V0['TblMrm0V0']['actual_meeting_date_time'])) ;
                                else echo "--"
                                ?></td>
			<tr>
				<th width="30%">Attainted By</th>
	<td><?php foreach(json_decode($tblMrm0V0['TblMrm0V0']['attainted_by'],true) as $field ){
                            echo $attaintedBies[$field] .", ";
                        };?></td>
			</tr>
			<tr>
				<th width="30%">Prepared By</th>
				<td><?php 
				$img = WWW_ROOT. DS. 'img'. DS . $this->Session->read('User.company_id'). DS .'signature'. DS. $tblMrm0V0['PreparedBy']["id"] . DS. 'sign.png';

                        
				if(file_exists($img)){
                            
				$imgUrl = Router::url('/', true).'/img/' . $this->Session->read('User.company_id') .'/signature/'. $tblMrm0V0['PreparedBy']["id"] . '/sign.png';
                            
				echo '<img src="'.$imgUrl.'" width="100"><br />';
                        
				}else if($tblMrm0V0['PreparedBy']['signature']){
                            
				echo '<img src="'.$tblMrm0V0['PreparedBy']['signature'].'" width="100"><br />';
                        }else{ echo '<small style="color:#810000">Signature not found</small><br />';} 
				?><?php echo $tblMrm0V0['PreparedBy']['name'];?></td>
			</tr>
			<tr>
				<th width="30%">Approved By</th>
				<td><?php 
				$img = WWW_ROOT. DS. 'img'. DS . $this->Session->read('User.company_id'). DS .'signature'. DS. $tblMrm0V0['ApprovedBy']["id"] . DS. 'sign.png';

                        
				if(file_exists($img)){
                            
				$imgUrl = Router::url('/', true).'/img/' . $this->Session->read('User.company_id') .'/signature/'. $tblMrm0V0['ApprovedBy']["id"] . '/sign.png';
                            
				echo '<img src="'.$imgUrl.'" width="100"><br />';
                        
				}else if($tblMrm0V0['ApprovedBy']['signature']){
                            
				echo '<img src="'.$tblMrm0V0['ApprovedBy']['signature'].'" width="100"><br />';
                        }else{ echo '<small style="color:#810000">Signature not found</small><br />';} 
				?><?php echo $tblMrm0V0['ApprovedBy']['name'];?></td>
			</tr>
			<tr>
				<th width="30%">Published?</th>
                                <td><?php echo $tblMrm0V0['TblMrm0V0']['publish']?'Yes':'No';?></td>
			</tr>
		</table>
        </div>
    </div>
</div>

<?php if($loadLinkedTables){ ?>
<div class="row">
    <div class="col-md-12">     
        <div id="linked_documents">         
        <?php   
            echo "<ul>";
            foreach($loadLinkedTables as $loadLinkedTable){ 
                echo "<li>" . $this->Html->link(Inflector::humanize($loadLinkedTable['name']),array(
                    'controller'=> $loadLinkedTable['table_name'],
                    'action'=>'index',
                    'custom_table_id'=>$loadLinkedTable['custom_table_id'],
                    'parent_record_id'=>$this->request->params['pass'][0],
                    'qc_document_id'=> $loadLinkedTable['qc_document_id']

                )) . "</li>";
            } 
            echo "</ul>"; ?>                
        </div>  
    </div>
</div>
<?php } ?>
<script type="text/javascript">
    $( function() {
        $( "#linked_documents" ).tabs({
          beforeLoad: function( event, ui ) {
            ui.jqXHR.fail(function() {
              ui.panel.html(
                "Couldn't load this tab. We'll try to fix this as soon as possible. " +
                "If this wouldn't be a demo." );
            });
          }
        });
      } );
</script>

<div class="row">
    <div class="col-md-12">
        <?php 
           foreach($linkedTables as $linkedTable){?>
        <div><h4>Linked Table : <?php echo Inflector::humanize($linkedTable['CustomTable']['name']);?></h4></div>
        <div id="<?php echo $linkedTable['CustomTable']['table_name']?>"></div>
        <script type="text/javascript">
            $("#<?php echo $linkedTable['CustomTable']['table_name']?>").load("<?php echo Router::url('/', true); ?><?php echo $linkedTable['CustomTable']['table_name']?>/index/<?php echo $this->request->params['pass'][0];?>/custom_table_id:<?php echo $linkedTable['CustomTable']['id'];?>");
        </script>
    <?php } ?>
    </div>
</div>
<?php if($loadLinkedTables){ ?>
<div class="row">
    <div class="col-md-12">     
        <div id="linked_documents_add">         
        <?php   
            echo "<ul>";
            foreach($loadLinkedTables as $loadLinkedTable){ 
                if($loadLinkedTable['action'] != 'index'){
                    echo "<li>" . $this->Html->link(Inflector::humanize($loadLinkedTable['name']),array(
                        'controller'=> $loadLinkedTable['table_name'],
                        'action'=>$loadLinkedTable['action'],
                        'custom_table_id'=>$loadLinkedTable['custom_table_id'],
                        'parent_record_id'=>$this->request->params['pass'][0],
                        'qc_document_id'=> $loadLinkedTable['qc_document_id'],
                    )) . "</li>";
                }               
            } 
            echo "</ul>"; ?>                
        </div>  
    </div>
</div>
<?php } ?>
<script type="text/javascript">
    $( function() {
        $( "#linked_documents_add" ).tabs({
          beforeLoad: function( event, ui ) {
            ui.jqXHR.fail(function() {
              ui.panel.html(
                "Couldn't load this tab. We'll try to fix this as soon as possible. " +
                "If this wouldn't be a demo." );
            });
          }
        });
      } );
</script>


<div class="row">
    <div class="col-md-12">
        <?php echo $this->element('approval_history',array('approval'=>$approval,'approvals'=>$approvals,'current_approval'=>$this->request->params['named']['approval_id'],'approvalComments',$approvalComments));?>
    </div>
    <div class="col-md-12">
        <?php echo $this->element('approval_form',array('approval'=>$approval));?>        
    </div>
</div>