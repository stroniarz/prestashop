<?php
require '../../config/config.inc.php';
require_once '../../init.php';

$products = Db::getInstance()->executeS("SELECT p.id_product, pl.name, p.ean13, ps.price, sa.quantity, cl.name as cName, m.name as bName,
(SELECT t.rate FROM ps_tax t
	WHERE t.id_tax =
 		(SELECT id_tax FROM ps_tax_rule
         	WHERE id_tax_rules_group = p.id_tax_rules_group AND id_country ='14'))
  as taxRate, pl.description, pl.link_rewrite, ps.wholesale_price FROM ps_product p
  LEFT JOIN ps_product_shop ps ON ps.id_product = p.id_product
  LEFT JOIN ps_category_lang cl ON cl.id_category = p.id_category_default
  LEFT JOIN ps_stock_available sa ON sa.id_product = p.id_product
  LEFT JOIN ps_product_lang pl ON pl.id_product = p.id_product
  LEFT JOIN ps_manufacturer m ON m.id_manufacturer = p.id_manufacturer
  WHERE p.active='1' AND pl.id_lang = '1' AND sa.id_product_attribute ='0'");

// Filter the excel data
function filterData(&$str){
    $str = preg_replace("/\t/", "\\t", $str);
    $str = preg_replace("/\r?\n/", "\\n", $str);
    if(strstr($str, '"')) $str = '"' . str_replace('"', '""', $str) . '"';
}

// Excel file name for download
$fileName = "emag_products_" . date('Y-m-d') . ".xls";

// Column names

$optional = array('Optional', 'Optional', 'Optional', 'Optional', 'Optional', 'Optional', 'Optional', 'Optional', 'Mandatory', 'Mandatory', 'Mandatory', 'Mandatory', 'Mandatory', 'Mandatory');
$fields = array('Product ID', 'Category', 'EAN', 'Price without VAT', 'VAT', 'STOCK', 'Min sale price', 'Max sale price', 'Product  name', 'Brand', 'Description', 'Products URL', 'Main image URL', 'Secondary image URL', 'Secondary image URL', 'Secondary image URL', 'Secondary image URL', 'Secondary image URL');

// Display column names as first row
$excelData = implode("\t", array_values($optional)) . "\n";
$excelData .= implode("\t", array_values($fields)) . "\n";


 foreach ($products as $product) {
   $images = getImg($product['id_product']);
   $image = array();
   foreach ($images as $img) {
     array_push($image,$img['url_image']);
   }
   $link = new Link();
   $link = $link->getProductLink($product['id_product']);
           $lineData = array($product['id_product'], $product['cName'], $product['ean13'], $product['price'], $product['taxRate'], $product['quantity'], $product['wholesale_price'], $product['wholesale_price'], $product['name'], $product['bName'], $product['description'], $link);
           array_walk($lineData, 'filterData');
           $excelData .= implode("\t", array_values($lineData)) . "\t" . implode("\t", array_values($image)) . "\n";
 }


 function getImg($id_product){
   $url = _PS_BASE_URL_;
   $images =  Db::getInstance()->executeS("SELECT cover ,concat('$url/img/p/',mid(im.id_image,1,1),'/',
  if (length(im.id_image)>1,concat(mid(im.id_image,2,1),'/'),''),
  if (length(im.id_image)>2,concat(mid(im.id_image,3,1),'/'),''),
  if (length(im.id_image)>3,concat(mid(im.id_image,4,1),'/'),''),
  if (length(im.id_image)>4,concat(mid(im.id_image,5,1),'/'),''),im.id_image, '.jpg' ) AS url_image
  FROM ps_image im
  WHERE im.id_product ='$id_product'",true,false);
  return $images;
 }


// Download the file
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$fileName\"");
echo $excelData;
exit;
