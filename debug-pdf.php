<?php
$order_id = wc_get_orders(array('limit'=>1,'return'=>'ids')) ?: null;
if(!$order_id){ $o = wc_create_order(array('status'=>'quote-request')); $o->add_product(wc_get_product(wc_get_products(array('limit'=>1,'return'=>'ids'))[0]),2); $o->calculate_totals(); $o->save(); $order_id = $o->get_id(); }
try {
  $p = WSQ_PDF_Generator::instance()->generate( $order_id );
  echo "OK: ".basename($p);
} catch (\Throwable $e) {
  echo "THROW: ".get_class($e)." :: ".$e->getMessage()."\n".$e->getTraceAsString();
}
