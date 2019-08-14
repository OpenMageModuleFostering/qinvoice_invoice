<?php
//class NameSpaceName_ModuleName_Model_ObserverDir_Observer
class Qinvoice_Invoice_Order_Observer
{
    public function __contruct()
    {
       
    }

    public function sendOnOrder($observer){
        $order = $observer->getEvent()->getOrder(); 

        // GETTING TRIGGER SETTING
        $db = Mage::getSingleton('core/resource')->getConnection('core_write');             
        $varPath = 'invoice_options/invoice/invoice_on_order';
        $resultTwo = $db->query("SELECT value FROM core_config_data WHERE path LIKE '".$varPath."'");
        $rowTwo = $resultTwo->fetch(PDO::FETCH_ASSOC);
        $varOnOrder = $rowTwo['value'];

        if($varOnOrder == 1){
            $this->createInvoiceForQinvoice($order->getId(), false);
        }else{
            return true;
        }
    }
    /**
     * Exports new orders to an xml file
     * @param Varien_Event_Observer $observer
     * @return Feed_Sales_Model_Order_Observer
     */
    public function sendOnPayment($observer){
        // Gets called even when other payment method is choosen.
        
        $order_ids = $observer->getEvent()->getOrderIds(); 
        $order = $observer->getEvent()->getOrder(); 

        // GETTING TRIGGER SETTING
        $db = Mage::getSingleton('core/resource')->getConnection('core_write');             
        $varPath = 'invoice_options/invoice/invoice_on_order';
        $resultTwo = $db->query("SELECT value FROM core_config_data WHERE path LIKE '".$varPath."'");
        $rowTwo = $resultTwo->fetch(PDO::FETCH_ASSOC);
        $varOnOrder = $rowTwo['value'];

        if($varOnOrder == 0){
            $this->createInvoiceForQinvoice($order_ids[0], true);
        }else{
            return true;
        }        
    }
    public function createInvoiceForQinvoice($varOrderID,$ifPaid = false)
    {
        $paid = 0;
        $db = Mage::getSingleton('core/resource')->getConnection('core_write'); 
        // GETTING ORDER ID
        //$resultOne = $db->query("SELECT max(entity_id) as LastOrderID FROM sales_flat_order");
        //$rowOne = $resultOne->fetch(PDO::FETCH_ASSOC);
            
        //$varOrderID = $rowOne['LastOrderID'];
        
        $varCurrenyCode =  Mage::app()->getStore()->getCurrentCurrency()->getCode();
        // GETTING ORDER STATUS
        $resultOne = $db->query("SELECT entity_id, status, customer_email, base_currency_code, shipping_description, shipping_amount, shipping_tax_amount, increment_id, grand_total, total_paid FROM sales_flat_order WHERE entity_id=".$varOrderID);
        $rowOne = $resultOne->fetch(PDO::FETCH_ASSOC);
        
        
        if($rowOne['status'] == 'processing' || $rowOne['status'] == 'complete' || $rowOne['total_paid'] == $rowOne['grand_total'])
        {
            $varStatus = 'Paid';
            // GETTING API URL
            $varURLPath = 'invoice_options/invoice/paid_remark';
            $resultURL = $db->query("SELECT value FROM core_config_data WHERE path LIKE '".$varURLPath."'");
            $rowURL = $resultURL->fetch(PDO::FETCH_ASSOC);
            $paid_remark = $rowURL['value'];
            $paid = 1;
        }
        else
        {
            if($ifPaid == true){
                // cancel if invoice has to be paid
                return;
            }
            $paid_remark = '';
            $varStatus = 'Sent';
        }
        
        $result = $db->query("SELECT item_id, product_type, product_options, order_id, sku, name, description, qty_ordered, base_price, tax_percent, tax_amount, base_discount_amount FROM sales_flat_order_item WHERE order_id=".$varOrderID." AND parent_item_id IS NULL GROUP BY sku HAVING (order_id > 0) ORDER BY item_id desc");
        
        if(!$result) {
            //return false;
        }
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $arrData[] = $row;
            }
        if(!$arrData) {
            //return false;
        }
        //$comment = '';
        //$comment = $data['comment_text'];
        // getting po_number
        $random_number = rand(0, pow(10, 7));

        // GETTING API USERNAME
        $varPath = 'invoice_options/invoice/api_username';
        $resultTwo = $db->query("SELECT value FROM core_config_data WHERE path LIKE '".$varPath."'");
        $rowTwo = $resultTwo->fetch(PDO::FETCH_ASSOC);
        $username = $rowTwo['value'];

        // GETTING API PASSWORD
        $varPath = 'invoice_options/invoice/api_password';
        $resultTwo = $db->query("SELECT value FROM core_config_data WHERE path LIKE '".$varPath."'");
        $rowTwo = $resultTwo->fetch(PDO::FETCH_ASSOC);
        $password = $rowTwo['value'];

        // GETTING LAYOUT CODE
        $varPath = 'invoice_options/invoice/layout_code';
        $resultTwo = $db->query("SELECT value FROM core_config_data WHERE path LIKE '".$varPath."'");
        $rowTwo = $resultTwo->fetch(PDO::FETCH_ASSOC);
        $layout_code = $rowTwo['value'];

        
        // GETTING CLIENT DETAILS
        $resultThree = $db->query("SELECT firstname, lastname, company, email, telephone, street, city, region, postcode FROM sales_flat_order_address WHERE email='".$rowOne['customer_email']."'");
        $rowThree = $resultThree->fetch(PDO::FETCH_ASSOC);

        $invoice = new qinvoice($username,$password);

        $invoice->companyname = $rowThree['firstname'].' '.$rowThree['lastname'];       // Your customers company name
        $invoice->contactname = $rowThree['firstname'].' '.$rowThree['lastname'];       // Your customers contact name
        $invoice->email = $rowOne['customer_email'];                // Your customers emailaddress (invoice will be sent here)
        $invoice->address = $rowThree['street'];                // Self-explanatory
        $invoice->zipcode = $rowThree['postcode'];              // Self-explanatory
        $invoice->city = $rowThree['city'];                     // Self-explanatory
        $invoice->country = '';                 // 2 character country code: NL for Netherlands, DE for Germany etc
        $invoice->vat = '';                     // Self-explanatory
        $invoice->paid = $paid;
         
        $varRemarkPath = 'invoice_options/invoice/invoice_remark';
        $resultRemark = $db->query("SELECT value FROM core_config_data WHERE path LIKE '".$varRemarkPath."'");
        $rowRemark = $resultRemark->fetch(PDO::FETCH_ASSOC);
        $invoice_remark = $rowRemark['value'];
        $invoice->remark = str_replace('{order_id}',$rowOne['increment_id'],$invoice_remark) .' '. $paid_remark;                  // Self-explanatory

        $varSendPath = 'invoice_options/invoice/send_mail';
        $resultSend = $db->query("SELECT value FROM core_config_data WHERE path LIKE '".$varSendPath."'");
        $rowSend = $resultSend->fetch(PDO::FETCH_ASSOC);
        $send_mail = $rowSend['value'];

        $varLayoutPath = 'invoice_options/invoice/layout_code';
        $resultLayout = $db->query("SELECT value FROM core_config_data WHERE path LIKE '".$varLayoutPath."'");
        $rowLayout = $resultLayout->fetch(PDO::FETCH_ASSOC);
        $invoice_layout = $rowLayout['value'];

        $invoice->setLayout($invoice_layout);

        $varTagPath = 'invoice_options/invoice/invoice_tag';
        $resultTag = $db->query("SELECT value FROM core_config_data WHERE path LIKE '".$varTagPath."'");
        $rowTag = $resultTag->fetch(PDO::FETCH_ASSOC);
        $invoice_tag = $rowTag['value'];

        $invoice->send = $send_mail;

        // OPTIONAL: Add tags
        $invoice->addTag($rowOne['increment_id']);
        $invoice->addTag($invoice_tag);
      //  $invoice->addTag('send: '. $send_mail);
      //  $invoice->addTag('paid: '. $paid .' '. $rowOne['total_paid']);


            for($i=0;$i<count($arrData);$i++)
            {  
                $arrItemOptions = unserialize($arrData[$i]['product_options']);

                $varDescription = '';
                if(@$arrItemOptions['options'])
                {
                    for($k=0; $k <count($arrItemOptions['options']); $k++)
                    {
                        $varDescription .= $arrItemOptions['options'][$k]['label'].": ".$arrItemOptions['options'][$k]['print_value']."\n";
                    }
                }
                else
                if(@$arrItemOptions['attributes_info'])
                {
                    for($k=0; $k <count($arrItemOptions['attributes_info']); $k++)
                    {
                        $varDescription .= $arrItemOptions['attributes_info'][$k]['label'].": ".$arrItemOptions['attributes_info'][$k]['value']."\n";
                    }
                }
                else
                {
                    $varDescription = "[".$arrData[$i]['sku']."] ".trim($arrData[$i]['name']);
                }
                $params = array(    
                    'description' => $arrData[$i]['name'] ."\n". $varDescription,
                    'price' => $arrData[$i]['base_price']*100,
                    'vatpercentage' => trim(number_format($arrData[$i]['tax_percent'],2,'.', ''))*100,
                    'discount' => trim(number_format($arrData[$i]['base_discount_amount'], 2, '.', '')/$arrData[$i]['base_price'])*100,
                    'quantity' => $arrData[$i]['qty_ordered']*100,
                    'categories' => ''
                    );
                //mail('casper@expertnetwork.nl', 'vat', $arrData[$i]['tax_percent']);
                $invoice->addItem($params);

            }
            if($rowOne['shipping_amount'] > 0)
            {
                $params = array(    
                    'description' => trim($rowOne['shipping_description']),
                    'price' => $rowOne['shipping_amount']*100,
                    'vatpercentage' => ($rowOne['shipping_tax_amount']/$rowOne['shipping_amount'])*100,
                    'discount' => 0,
                    'quantity' => 100,
                    'categories' => 'shipping'
                    );

                $invoice->addItem($params);
                
            }
            
    
            $result =  $invoice->sendRequest();
            if($result == 1){
                //notify_to_admin('Casper Mekel','casper@newday.sk','Invoice generated!');
            }else{
                //notify_to_admin('Casper Mekel','casper@newday.sk','Something went wrong!');
            }
            return true;
        

        //$curlInvoiveResult = $this->sendCurlRequest($createInvoiceXML);
        
        // GETTING SEND MAIL SETTING
        $db = Mage::getSingleton('core/resource')->getConnection('core_write');             
        $varPath = 'invoice_options/invoice/send_mail';
        $resultTwo = $db->query("SELECT value FROM core_config_data WHERE path LIKE '".$varPath."'");
        $rowTwo = $resultTwo->fetch(PDO::FETCH_ASSOC);
        $varSendMailFlag = $rowTwo['value'];
        
        if($varSendMailFlag && 1==2)
        {               
            $xml = stripslashes($curlInvoiveResult);
            $objXml = new SimpleXMLElement($xml);                
            $arrParamList = $this->objectsIntoArray($objXml);
            
            if($arrParamList['@attributes']['status'] == '200')
            {
                $varInvoiceID = $arrParamList['invoice_id'];
                
                $varSendInvoiceXml = '<?xml version="1.0" encoding="utf-8"?>
                <request method="sendInvoiceMail">
                    <invoice_id>'.$varInvoiceID.'</invoice_id>
                </request>';
                $curlInvoiceSendResult = $this->sendCurlRequest($varSendInvoiceXml);
                
            }
        
        }
    }
    
    public function notify_to_admin($name, $email, $msg) 
    {
        $varSubject = 'Qinvoice Notification';
                
        //Mage::log($msg);
                    
        $mail = Mage::getModel('core/email');
        $mail->setToName($name);
        $mail->setToEmail($email);
        $mail->setBody($msg);
        $mail->setSubject($varSubject);
        $mail->setFromEmail("support@qinvoice.com");
        $mail->setFromName("Qinvoice Development");
        $mail->setType('text');
        $mail->send();
    }
}

class qinvoice{

    protected $gateway = '';
    private $username;
    private $password;

    public $companyname;
    public $contactname;
    public $email;
    public $address;
    public $city;
    public $country;
    public $vatnumber;
    public $remark;
    public $paid;
    public $send;

    public $layout;
    
    private $tags = array();
    private $items = array();
    private $files = array();
    private $recurring;

    function __construct($username, $password){
        $this->username = $username;
        $this->password = $password;
        $this->recurring = 'none';

        $db = Mage::getSingleton('core/resource')->getConnection('core_write');
        
        // GETTING API URL
        $varURLPath = 'invoice_options/invoice/api_url';
        $resultURL = $db->query("SELECT value FROM core_config_data WHERE path LIKE '".$varURLPath."'");
        $rowURL = $resultURL->fetch(PDO::FETCH_ASSOC);
        $apiURL = $rowURL['value'];

        $this->gateway = $apiURL;
    }

    public function addTag($tag){
        $this->tags[] = $tag;
    }

    public function setLayout($code){
        $this->layout = $code;
    }

    public function setRecurring($recurring){
        $this->recurring = strtolower($recurring);
    }

    public function addItem($params){
        $item['description'] = $params['description'];
        $item['price'] = $params['price'];
        $item['vatpercentage'] = $params['vatpercentage'];
        $item['discount'] = $params['discount'];
        $item['quantity'] = $params['quantity'];
        $item['categories'] = $params['categories'];
        $this->items[] = $item;
    }
    
    public function addFile($name, $url){
        $this->files[] = array('url' => $url, 'name' => $name);
    }

    public function sendRequest() {
        $content = "<?xml version='1.0' encoding='UTF-8'?>";
        $content .= $this->buildXML();

        $headers = array("Content-type: application/atom+xml");
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->gateway );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        $data = curl_exec($ch);
        if (curl_errno($ch)) {
            print curl_error($ch);
        } else {
            curl_close($ch);
        }
        if($data == 1){
            return true;
        }else{
            return false;
        }
        
    }

    private function buildXML(){
        $string = '<request>
                        <login mode="newInvoice">
                            <username>'.$this->username.'</username>
                            <password>'.$this->password.'</password>
                        </login>
                        <invoice>
                            <companyname>'. $this->companyname .'</companyname>
                            <contactname>'. $this->contactname .'</contactname>
                            <email>'. $this->email .'</email>
                            <address>'. $this->address .'</address>
                            <zipcode>'. $this->zipcode .'</zipcode>
                            <city>'. $this->city .'</city>
                            <country>'. $this->country .'</country>
                            <vat>'. $this->vatnumber .'</vat>
                            <recurring>'. $this->recurring .'</recurring>
                            <remark>'. $this->remark .'</remark>
                            <layout>'. $this->layout .'</layout>
                            <paid>'. $this->paid .'</paid>
                            <send>'. $this->send .'</send>
                            <tags>';
        foreach($this->tags as $tag){
            $string .= '<tag>'. $tag .'</tag>';
        }
                    
        $string .= '</tags>
                    <items>';
        foreach($this->items as $i){

            $string .= '<item>
                <quantity>'. $i['quantity'] .'</quantity>
                <description>'. $i['description'] .'</description>
                <price>'. $i['price'] .'</price>
                <vatpercentage>'. $i['vatpercentage'] .'</vatpercentage>
                <discount>'. $i['discount'] .'</discount>
                <categories>'. $i['categories'] .'</categories>
                
            </item>';
        }
                       
        $string .= '</items>
                    <files>';
        foreach($this->files as $f){
            $string .= '<file url="'.$f['url'].'">'.$f['name'].'</file>';
        }
        $string .= '</files>
                </invoice>
            </request>';
        return $string;
    }
}

 ?>