<?php
/**
*  @author    Martin Tomasek
*  @copyright DiffSolutions, s.r.o.
*  @license   https://creativecommons.org/licenses/by-sa/4.0/ CC BY-SA 4.0
*/

include_once('fcommon.php');
include_once('fcustomer.php');

function group_order_details($orders)
{
    $os = array();
    $oid = null;
    $acc = array();
    foreach ($orders as $order) {
        $order_id = $order['id_order'];
        if ($oid != $order_id) {
            if (isset($oid)) {
                $os[$oid] = $acc;
            }
            $acc = array();
            $oid = $order_id;
        }
        $acc[] = $order;
    }
    return $os;
}

class FOrder
{
    public function __construct($w, $common)
    {
    // Get date from config if not set assing 1970
        $today =  date('Y-m-d H:i:s');
        $date_from = Configuration::get('SAMBA_DATE');
        if(empty($date_from)){
          $date_from = '1970-01-01';
        }
        $this->w = $w;
        $this->common = $common;
        #$LANG_ID = $common->getConfig('LANG_ID');
        #$SHOP_ID = $common->getConfig('SHOP_ID');
        $this->q = "select o.*, a.*, od.* from ".$common->tname('orders', 'o')." ".
                $common->tjoin('address', 'a').' on o.id_address_delivery = a.id_address '.
                $common->tjoin('order_detail', 'od').' on o.id_order = od.id_order '.
                $common->tjoin('customer', 'cu').' on o.id_customer = cu.id_customer '.
                ' where o.id_order > 50 AND o.module!="x13allegro"
                AND o.date_add BETWEEN "' . $date_from . '" AND "' . $today . '"
              order by o.id_order';
    }

    public function genFeedPage($page, $cust_email, $cust_valid)
    {
	    $pagesize = 1000;
	    $w = $this->w;
	    $common = $this->common;
	    $variant_ids = $common->config['VARIANT_IDS'];
        $orders = Db::getInstance()->ExecuteS($this->q . '  limit 1000 offset ' . $page * $pagesize);
        $orders = group_order_details($orders);
        foreach ($orders as $order_id => $ods) {
            $o = $ods[0];
            $order_id = $o['id_order'];
            $customer_id = (int)$o['id_customer'];

            if (!array_key_exists($customer_id, $cust_valid)) {
                continue; #GDPR
            }
            $xw = $w->startLn();
            #$customer_id = base64_encode($o['id_customer']);
            // $status = $common->orderStatus((int)($o['current_state']));
            $status = $this->getStatus(($o['current_state']));
            $xw->writeElement('ORDER_ID', $order_id);
            $xw->writeElement('CUSTOMER_ID', $customer_id);
            $xw->writeElement('CREATED_ON', $common->dtIso($o['date_add']));
            if (array_key_exists($customer_id, $cust_email)) {
                $xw->writeElement('EMAIL', $cust_email[$customer_id]);
            }
            if ($status == 'finished') {
                $xw->writeElement('FINISHED_ON', $common->dtIso($o['delivery_date']));
            }
            $xw->writeElement('STATUS', $status);
            if (!empty($o['postcode'])) {
                $xw->writeElement('ZIP_CODE', $o['postcode']);
            }
            if (!empty($o['phone_mobile'])) {
                $xw->writeElement('PHONE', $o['phone_mobile']);
            }
            $xw->startElement('ITEMS');
            foreach ($ods as $od) {
		    $xw->startElement('ITEM');
                if ($variant_ids){
                    $xw->writeElement('PRODUCT_ID', $od['product_id'].'-'.$od['product_attribute_id']);
                }
                else {
                    $xw->writeElement('PRODUCT_ID', $od['product_id'].'-0');
                }
                $xw->writeElement('PRICE', $od['total_price_tax_incl']);
                $xw->writeElement('AMOUNT', $od['product_quantity']);
                $xw->endElement();
            }
            $xw->endElement();
            $w->endLn();
	}
	return count($orders);
    }
  // Assign status by client choice
    private function getStatus($value){
      $arr_create=explode(',',Configuration::get('SAMBA_ORDER_CREATE'));
      $arr_finished=explode(',',Configuration::get('SAMBA_ORDER_FINISHED'));
      $arr_cancled=explode(',',Configuration::get('SAMBA_ORDER_CANCLED'));
      if (in_array($value,$arr_create))
        return 'created';
      else if (in_array($value,$arr_finished))
        return 'finished';
      else if (in_array($value,$arr_cancled))
        return 'canceled';
      else
        return 'created';
    }
    public function genFeed()
    {
        $w = $this->w;
        $common = $this->common;

        $customer = new FCustomer($w, $common, false);
        $customer_info = $customer->genFeed();
        $cust_email = $customer_info['email'];
  	$cust_valid = $customer_info['valid'];

  	$l = 1;
  	$pageno = 0;
  	while($l){
  		$l = $this->genFeedPage($pageno, $cust_email, $cust_valid);
  		$pageno += 1;
  	}

        $w->end();
        #return $orders;
    }
}
