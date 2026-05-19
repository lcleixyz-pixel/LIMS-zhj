<?php

App::import('Core', 'Controller');

App::import('Controller','reports');
App::import('Controller','users');

App::import('Vendor', 'PHPExcel', array(
    'file' => 'Excel/PHPExcel.php'
));

App::uses('Folder', 'Utility');
App::uses('File', 'Utility');

class PasswordReminderShell extends AppShell {
    
    public function main() {

        $this->out('It works!');
		 
    }
}
/* "$HOME/html/demo-application/app/Console/cake" report -cli "/web/cgi-bin/" -console  "$HOME/html/demo-application/app/Console/" -app "$HOME/html/app/" */
/* "$HOME/html/demo-application/app/Console/cake" hello -cli "/web/cgi-bin/" -console  "$HOME/html/demo-application/app/Console/" -app "$HOME/html/app/" */
?>