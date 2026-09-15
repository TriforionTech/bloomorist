<?php
$c = file_get_contents('app/Services/AccountingService.php');
$c = str_replace("'1010'", "'1101'", $c);
$c = str_replace("'4010'", "'4101'", $c);
$c = str_replace("'1030'", "'1104'", $c);
$c = str_replace("'5010'", "'6106'", $c);
file_put_contents('app/Services/AccountingService.php', $c);
echo "Updated COA codes successfully.\n";
