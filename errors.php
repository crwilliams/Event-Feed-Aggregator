<?
$d = dir('var/log/');
while (false !== ($entry = $d->read())) {
	$handle = @fopen('var/log/'.$entry, "r");
	if ($handle) {
		while (($buffer = fgets($handle, 4096)) !== false) {
			$p = explode("\t", $buffer, 2);
			if(count($p) < 2 || $p[0] < '2012-03-01' || trim($p[1]) == '') continue;
			@$data[trim($p[1])][$entry]++;
		}
		fclose($handle);
	}
}
ksort($data);
foreach($data as $error => $logs)
{
	foreach($logs as $log => $count)
	{
		echo $error."\t".$log."\t".$count."\n";
	}
}
$d->close();
?>
