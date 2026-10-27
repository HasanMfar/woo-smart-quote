<?php
$order_id = (wc_get_orders(array('limit'=>1,'return'=>'ids')) ?: array())[0] ?? null;
try {
  $p = WSQ_PDF_Generator::instance()->generate( $order_id );
  echo "PATH: ".var_export($p,true)."\n";
} catch (\Throwable $e) {
  echo "THROW: ".get_class($e)." :: ".$e->getMessage()." @ ".$e->getFile().":".$e->getLine()."\n";
}
echo "LOG:\n";
if (class_exists('WSQ_Logger')) { $logs = WSQ_Logger::all(10); print_r($logs); }
