<?php
$root=dirname(__DIR__);$routes=file_get_contents($root.'/public_html/routes/web.php');$nav=file_get_contents($root.'/public_html/app/Services/AdminNavigationRbacService.php');
$checks=['/admin/organization-chart','/admin/appointments','/admin/profile/organizational-context','OrganizationOperationsService','UserOrganizationalContextResolver'];
foreach($checks as $x){if(strpos($routes.$nav,$x)===false){fwrite(STDERR,"Missing: $x\n");exit(1);}}
if(strpos($routes,'DELETE /admin/appointments')!==false){exit(1);}echo "Organizational operations UI structural test passed.\n";
