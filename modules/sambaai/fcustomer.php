<?php
/**
*  @author    Martin Tomasek
*  @copyright DiffSolutions, s.r.o.
*  @license   https://creativecommons.org/licenses/by-sa/4.0/ CC BY-SA 4.0
*/

include_once('fcommon.php');

class FCustomer
{
    public function __construct($w, $common, $write = true)
    {
        $this->w = $w;
        $this->common = $common;
        $this->write = $write;
        $LANG_ID = $common->getConfig('LANG_ID');
        $this->q = 'select c.*, gl.name, a.postcode, a.phone, a.phone_mobile, min(a.id_address) as min_id '.
            ' from '.$common->tname('customer', 'c').' '.
	    $common->tleftjoin('gender_lang', 'gl').' on gl.id_gender = c.id_gender '.
            	' and gl.id_lang = '.$LANG_ID.' '.
            $common->tleftjoin('address', 'a').' on a.id_customer = c.id_customer '.
                 ' group by a.id_customer';
    }

    public function genFeedPage($page, &$customer_email, &$customer_valid)
    {
        $pagesize = 1000;
        $w = $this->w;
        $write = $this->write;
        $common = $this->common;
        $customers = Db::getInstance()->ExecuteS($this->q . ' order by c.id_customer limit 1000 offset ' . $page * $pagesize);
        foreach ($customers as $c) {
            #if ($c['optin'] != '1') {
            #    continue;
            #} #GDPR
            if ($c['deleted'] == '1') {
                continue;
            }

            if ($write) {
                $xw = $w->startLn();
                $xw->writeElement('FIRST_NAME', $c['firstname']);
                $xw->writeElement('LAST_NAME', $c['lastname']);
                $xw->writeElement('CUSTOMER_ID', $c['id_customer']);
                $xw->writeElement('EMAIL', $c['email']);
                $xw->writeElement('PHONE', $c['phone_mobile'] ? $c['phone_mobile'] : $c['phone']);
                $xw->writeElement('ZIP_CODE', $c['postcode']);
                $xw->writeElement('NEWSLETTER_FREQUENCY', $c['newsletter']?'every day':'never');
                $xw->writeElement('REGISTRATION', $common->dtIso($c['date_add']));

                $xw->startElement('PARAMETERS');
                $common->parameter($xw, 'Gender', $c['name']);
                $common->parameter($xw, 'Birthday', $c['birthday']);
                $xw->endElement();
            }

            $customer_email[$c['id_customer']] = $c['email'];
            $customer_valid[$c['id_customer']] = true;

            if ($write) {
                $w->endLn();
            }
        }
        return count($customers);
    }

    public function genFeed()
    {
	$w = $this->w;
        $write = $this->write;
        $common = $this->common;
        $customer_email = array();
        $customer_valid = array();
	#echo "query: ".$this->q.'\n';
	#
        $l = 1;
	$pageno = 0;
	while($l){
		$l = $this->genFeedPage($pageno, $customer_email, $customer_valid);
		$pageno += 1;
	}

        if ($write) {
            $w->end();
        }
        return array('email' => $customer_email, 'valid' => $customer_valid);
    }
}
