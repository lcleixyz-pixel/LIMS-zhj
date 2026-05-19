<div  id="main">
<?php echo $this->Session->flash();?>
<?php echo $this->element('nav-header-lists',array('postData'=>array('friendlyName'=>'Audit Findings','pluralHumanName'=>'tbl_audit_findings_0_v0s','modelClass'=>'TblAuditFindings0V0','options'=>array("finding_number"=>"Finding Number","audit_start_date"=>"Audit Start Date","audit_end_date"=>"Audit End Date","auditor"=>"Auditor","auditee"=>"Auditee","finding_type"=>"Finding Type","current_status"=>"Current Status","findings"=>"Findings","response_from_auditee"=>"Response From Auditee","finding_number"=>"Finding Number","audit_start_date"=>"Audit Start Date","audit_end_date"=>"Audit End Date","auditor"=>"Auditor","auditee"=>"Auditee","finding_type"=>"Finding Type","current_status"=>"Current Status","findings"=>"Findings","response_from_auditee"=>"Response From Auditee"),'pluralVar'=>'tblAuditFindings0V0s'))); ?>
<div id="load_parent">
    <?php if($tblAuditFindings0V0['TblAuditFindings0V0']['parent_id'] && isset($parent_table_name)){ ?>
    <script type="text/javascript">
        $().ready(function(){
            $.ajax({
                url: "<?php echo Router::url('/', true); ?><?php echo $parent_table_name;?>/view/<?php echo $tblAuditFindings0V0['TblAuditFindings0V0']['parent_id'];?>",
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
<div class="view-record"><h2><small>Finding Number: </small><?php echo $tblAuditFindings0V0['TblAuditFindings0V0']['finding_number'];?></h2>

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
				<th width="30%"> Finding Number</th>
                                <td><?php echo $tblAuditFindings0V0['TblAuditFindings0V0']['finding_number'];?></td>
			</tr>
			<tr>
				<th width="30%"> Audit Start Date</th>
                                <td><?php 
                                if($tblAuditFindings0V0['TblAuditFindings0V0']['audit_start_date'])echo  date(Configure::read('dateFormat'),strtotime($tblAuditFindings0V0['TblAuditFindings0V0']['audit_start_date'])) ;
                                else echo "--"
                                ?></td>
			<tr>
				<th width="30%"> Audit End Date</th>
                                <td><?php 
                                if($tblAuditFindings0V0['TblAuditFindings0V0']['audit_end_date'])echo  date(Configure::read('dateFormat'),strtotime($tblAuditFindings0V0['TblAuditFindings0V0']['audit_end_date'])) ;
                                else echo "--"
                                ?></td>
			<tr>
				<th width="30%">Auditor</th>
			<td>
				<?php 
				$img = WWW_ROOT. DS. 'img'. DS . $this->Session->read('User.company_id'). DS .'signature'. DS. $tblAuditFindings0V0['Auditor']["id"] . DS. 'sign.png';

                                
				if(file_exists($img)){
                                    
				$imgUrl = Router::url('/', true).'/img/' . $this->Session->read('User.company_id') .'/signature/'. $tblAuditFindings0V0['Auditor']["id"] . '/sign.png';
                                    
				echo '<img src="'.$imgUrl.'" width="100">';
                                
				}else if($tblAuditFindings0V0['Auditor']['signature']){
                                    
				echo '<img src="'.$tblAuditFindings0V0['Auditor']['signature'].'" width="100">';
                                }else{ echo '<small style="color:#810000">Signature not found</small>';} 
				?>
				<br /><?php echo $tblAuditFindings0V0['Auditor']['name'];?></td></tr>
			<tr>
				<th width="30%">Auditee</th>
			<td>
				<?php 
				$img = WWW_ROOT. DS. 'img'. DS . $this->Session->read('User.company_id'). DS .'signature'. DS. $tblAuditFindings0V0['Auditee']["id"] . DS. 'sign.png';

                                
				if(file_exists($img)){
                                    
				$imgUrl = Router::url('/', true).'/img/' . $this->Session->read('User.company_id') .'/signature/'. $tblAuditFindings0V0['Auditee']["id"] . '/sign.png';
                                    
				echo '<img src="'.$imgUrl.'" width="100">';
                                
				}else if($tblAuditFindings0V0['Auditee']['signature']){
                                    
				echo '<img src="'.$tblAuditFindings0V0['Auditee']['signature'].'" width="100">';
                                }else{ echo '<small style="color:#810000">Signature not found</small>';} 
				?>
				<br /><?php echo $tblAuditFindings0V0['Auditee']['name'];?></td></tr>
			<tr>
				<th width="30%">Finding Type</th>
	<td><?php echo $customArray['findingTypes'][$tblAuditFindings0V0['TblAuditFindings0V0']['finding_type']];?></td>
			</tr>
			<tr>
				<th width="30%">Current Status</th>
	<td><?php echo $customArray['currentStatuses'][$tblAuditFindings0V0['TblAuditFindings0V0']['current_status']];?></td>
			</tr>
			<tr>
				<th width="30%"> Findings</th>
                                <td><?php echo $tblAuditFindings0V0['TblAuditFindings0V0']['findings'];?></td>
			</tr>
			<tr>
				<th width="30%"> Response From Auditee</th>
                                <td><?php echo $tblAuditFindings0V0['TblAuditFindings0V0']['response_from_auditee'];?></td>
			</tr>
			<tr>
				<th width="30%">Prepared By</th>
				<td><?php 
				$img = WWW_ROOT. DS. 'img'. DS . $this->Session->read('User.company_id'). DS .'signature'. DS. $tblAuditFindings0V0['PreparedBy']["id"] . DS. 'sign.png';

                        
				if(file_exists($img)){
                            
				$imgUrl = Router::url('/', true).'/img/' . $this->Session->read('User.company_id') .'/signature/'. $tblAuditFindings0V0['PreparedBy']["id"] . '/sign.png';
                            
				echo '<img src="'.$imgUrl.'" width="100"><br />';
                        
				}else if($tblAuditFindings0V0['PreparedBy']['signature']){
                            
				echo '<img src="'.$tblAuditFindings0V0['PreparedBy']['signature'].'" width="100"><br />';
                        }else{ echo '<small style="color:#810000">Signature not found</small><br />';} 
				?><?php echo $tblAuditFindings0V0['PreparedBy']['name'];?></td>
			</tr>
			<tr>
				<th width="30%">Approved By</th>
				<td><?php 
				$img = WWW_ROOT. DS. 'img'. DS . $this->Session->read('User.company_id'). DS .'signature'. DS. $tblAuditFindings0V0['ApprovedBy']["id"] . DS. 'sign.png';

                        
				if(file_exists($img)){
                            
				$imgUrl = Router::url('/', true).'/img/' . $this->Session->read('User.company_id') .'/signature/'. $tblAuditFindings0V0['ApprovedBy']["id"] . '/sign.png';
                            
				echo '<img src="'.$imgUrl.'" width="100"><br />';
                        
				}else if($tblAuditFindings0V0['ApprovedBy']['signature']){
                            
				echo '<img src="'.$tblAuditFindings0V0['ApprovedBy']['signature'].'" width="100"><br />';
                        }else{ echo '<small style="color:#810000">Signature not found</small><br />';} 
				?><?php echo $tblAuditFindings0V0['ApprovedBy']['name'];?></td>
			</tr>
			<tr>
				<th width="30%">Published?</th>
                                <td><?php echo $tblAuditFindings0V0['TblAuditFindings0V0']['publish']?'Yes':'No';?></td>
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