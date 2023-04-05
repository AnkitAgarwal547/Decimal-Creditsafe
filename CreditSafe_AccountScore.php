<?php

class CreditSafe
{

    private $api_url = 'https://connect.creditsafe.com/v1/';

    private $language = 'en';
    private $template = 'full';


    private $unauthenticatedCount;

    private $header = array(
        'Accept: application/json',
        "cache-control: no-cache",
        "Content-Type: application/json",
    );

    protected $credentials = array(
        "username" => 'test@test.com',
        "password" => '##################'
    );


    private function creditSafeProcessApi($uri, $method, $data = null)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->api_url . $uri,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => $this->header
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $responseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($err) {
            return array('success' => "false", 'message' => "cURL Error #:" . $err);
        } else {

            return $this->processResponse(json_decode($response, true), $responseCode, $uri, $method, $data);

        }
    }

   
    private function processResponse($responseData, int $responseCode, $uri, $method, $data)
    {
        $issueCodes = 'Server Error';
        $outcome = 'Something Went Wrong!!';

        if (is_array($responseData)) {
            if ($responseCode == 200) {
                return array('success' => 'true', 'code' => $responseCode, 'data' => $responseData);
            } elseif ($responseCode == 401) {

                if ($this->unauthenticatedCount < 2) {

                    $this->unauthenticatedCount++;
                    return $this->processApiWithAuth($uri, $method, true, $data);
                }

                return array('success' => 'false', 'code' => $responseCode, 'message' => 'It seems the auth credentials are changed to connect to third party. Contact Admin.');

            } else {

                return array('success' => 'false', 'code' => $responseCode, 'message' => ($responseData['details'] ?? $responseData['message'] ?? $outcome));
            }
        }

        return array('success' => 'false', 'code' => 500, 'message' => $outcome . ' - ' . $issueCodes);
    }


    private function login()
    {
        $uri = 'authenticate';

        $response = $this->creditSafeProcessApi($uri, 'POST', json_encode($this->credentials));

        if ($response['success'] === 'true' && isset($response['data']['token'])) {

            $_SESSION['auth_token'] = $response['data']['token'];
            $this->header[] = 'Authorization:' . $_SESSION['auth_token'];
        }

    }

    private function processApiWithAuth($uri, $method, $authRequired = false, $data = array())
    {
        if ($authRequired) {
            $this->login();
        } else {
            if (isset($_SESSION['auth_token'])) {
                $this->header[] = 'Authorization:' . $_SESSION['auth_token'];
            } else {
                $this->login();
            }
        }

        return $this->creditSafeProcessApi($uri, $method, $data);
    }
	

    public function fetchReport($companyId,$auth=true)
    {
        $uri = "companies/$companyId";


        $data = array(
            'language=' . $this->language,
            'template=' . $this->template,
        );

        $uri = $uri . '?' . implode('&', $data);
        if ($auth) {
            return $this->processApiWithAuth($uri, 'GET', false,$data);
        }
        return $this->creditSafeProcessApi($uri, 'GET', $data);
    }

    public function searchCompanies($companyRegNo)
    {
        $uri = "companies";

            $data = array(
                'language=' . $this->language,
                'countries=' . 'GB',
                'regNo=' . $companyRegNo
            );

        $uri = $uri . '?' . implode('&', $data);

        return $this->processApiWithAuth($uri, 'GET', false, $data);
    }

}



class AccountScore
{

    private $api_url = 'https://api.accountscore.net/api/';

    private $unauthenticatedCount;

    private $header = array(
        'Accept: application/json',
    );

    /////Decimal Factor production creds
    protected $credentials = array(
        'username' => 'test',
        'password' => '###############3',
        'clientId' => '#################'
    );

    private function asDataApi($uri, $method, $data = null)
    {


        $curl = curl_init();
        if (is_array($data)) {
            $header= array_merge($this->header,array("Content-Type: multipart/form-data"));
        }else{
            $header= array_merge($this->header,array("Content-Type: application/json"));
        }

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->api_url . $uri,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => $header
        ));

        $response = curl_exec($curl);


        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            return array('success' => "false", 'message' => "cURL Error #:" . $err);
        } else {
            if(!is_array(json_decode($response,true))){
                var_dump(json_decode($response,true),$uri,$data);exit;
            }



            return $this->processResponse(json_decode($response, true), $uri, $method, $data);

        }
    }


    private function processApiWithAuth($uri, $method, $authRequired = false, $data = array())
    {

        if ($authRequired) {
            $this->login();
        } else {
            if (isset($_SESSION['ocr_auth_token'])) {
                $this->header[] = 'Authorization:' . $_SESSION['ocr_auth_token'];
            } else {
                $this->login();
            }
        }

        return $this->asDataApi($uri, $method, $data);
    }


    private function processResponse(array $responseData, $uri, $method, $data)
    {
        $issueCodes = 'Server Error';
        $outcome = 'Error';


        if(isset($responseData['authToken']))
        {
            $response['success'] = 'true';
            $response['response']['idToken'] = $responseData['authToken'];
            return array('success' => 'true', 'authToken' => $responseData['authToken']);
        }


        if(isset($responseData['calibrationInfo']['customerId']))
        {
            return array('success' => 'true','response' => $responseData,'meta' => '200');
        }
        elseif ($responseData['message'] === 'An error has occurred.')
        {
            return array('success' => 'false','message' => 'The Account Score CUSTOMERID is not valid','meta' => '500');
        }
        elseif ($responseData['message'] === 'Authorization has been denied for this request.')
        {
            return array('success' => 'false','message' => 'The Account Score AUTH TOKEN is not valid','meta' => '401');
        }

        return array('success' => 'false', 'message' => $outcome . ' as - ' . $issueCodes);
    }



    private function createAccountCurl($uri, $method, $authRequired = false,$data = null)
    {

        if ($authRequired) {
            $this->login();
        } else {
            if (isset($_SESSION['ocr_auth_token'])) {
                $this->header[] = 'Authorization:' . $_SESSION['ocr_auth_token'];
            } else {
                $this->login();
            }
        }
        $curl = curl_init();
        $header= array_merge($this->header,array("Content-Type: application/json"));
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->api_url . $uri,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => $header
        ));

        $response = curl_exec($curl);

        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        $err = curl_error($curl);

        curl_close($curl);
        return array('response' => $response,'httpCode' => $httpcode);
    }

    public function createASAccount($lm_id)
    {
        $uri = "enrich/createCompany";

        $sql = "SELECT entity_id,full_data_response FROM lead_ocr_data WHERE lm_id = '$lm_id'";
        $leadOcrData = mysqli_query($connect_var, $sql);
        $rowNum = mysqli_num_rows($leadOcrData);

        ////If we have an entry in lead_ocr i.e if we have uploaded the bank statements for the company then only we would be able to create a new AS account
        if($rowNum != 0)
        {

            while($data = mysqli_fetch_assoc($leadOcrData))
            {
                $entityid = $data['entity_id'];
                $ocrFullData = $data['full_data_response'];
            }


            $ocrFullData = json_decode($ocrFullData,true);
            $numberOfMonthStatements = count($ocrFullData['result']);

            foreach ($ocrFullData['result'][0]['data'] as $datakey => $data)
            {
                if(($data['key'] == 'from_date'))
                {
                    $requestStartRange = $data['value'];
                }
                if(($data['key'] == 'account_number'))
                {
                    $ocrAccountNumber = $data['value'];
                }
                if(($data['key'] == 'sort_code'))
                {
                    $ocrSortCode = $data['value'];
                }
                if(($data['key'] == 'account_name'))
                {
                    $ocrAccountName = $data['value'];
                }
                if(($data['key'] == 'closing_balance'))
                {
                    $ocrAvailableBalance = $data['value'];
                }

            }

            foreach ($ocrFullData['result'][$numberOfMonthStatements-1]['data'] as $datakey => $data)
            {
                if(($data['key'] == 'to_date'))
                {
                    $requestEndRange = $data['value'];
                }
            }

            $fromDateArray = explode('/',$requestStartRange);
            $requestStartRange = $fromDateArray[2].'-'.$fromDateArray[1].'-'.$fromDateArray[0];

            $toDateArray = explode('/',$requestEndRange);
            $requestEndRange = $toDateArray[2].'-'.$toDateArray[1].'-'.$toDateArray[0];






            /////CREATING AN ARRAY FOR THE ACCOUNT DETAILS
            ///
            $currentBalance = $ocrAvailableBalance;
            $currentBalance = ltrim($currentBalance, '£');
            $currentBalance = floatval(str_replace(",","",$currentBalance));

            $ocrAccountBlock['accountId'] = $ocrAccountNumber;
            $ocrAccountBlock['accountName'] = $ocrAccountName;
            $ocrAccountBlock['sortCode'] = $ocrSortCode;
            $ocrAccountBlock['accountNumber'] = $ocrAccountNumber;
            $ocrAccountBlock['currentBalance'] = $currentBalance;
            $ocrAccountBlock['availableBalance'] = $currentBalance;



            //////CREATING AN ARRAY FOR TRANSACTIONS
            $ocrTransactionBlock = [];
            $statementArray = [];
            $transactionCounter = 1;
            foreach($ocrFullData as $result){
                if(is_array($result)){
                    foreach($result as $dataKey => $data){

                        if(isset($data['transactions']) && is_array($data['transactions'])){

                            foreach ($data['transactions'] as $transKey => $trans){
                                $statementArray[$transKey]['transactionId'] = $transactionCounter++;
                                ////INCASE OF CREDIT
                                if(isset($trans['paid_in']['value']) && $trans['paid_in']['value'] != ''){
                                    $statementArray[$transKey]['amount'] = abs($trans['paid_in']['value']);
                                }
                                ////INCASE OF DEBIT
                                else if (isset($trans['paid_out']['value']) && $trans['paid_out']['value'] != ''){
                                    $statementArray[$transKey]['amount'] =  -1 * abs($trans['paid_out']['value']);
                                }else{
                                    $statementArray[$transKey]['type'] = '';
                                }
                                $statementArray[$transKey]['description'] = $trans['description']['value'];

                                $date = str_replace('/', '-', $trans['date']['value']);
                                $statementArray[$transKey]['postDate'] = date('Y-m-d', strtotime($date));
                                $ocrTransactionBlock[$dataKey]['transactions'][$transKey] = $statementArray[$transKey];
                            }
                        }

                    }
                }

            }
            $finalTransactionBlock = [];
            $transObjectCounter = count($ocrTransactionBlock);
            for($x=0;$x<$transObjectCounter;$x++)
            {
                $finalTransactionBlock = array_merge($finalTransactionBlock,$ocrTransactionBlock[$x]['transactions']);

            }

            ////GET THE DATA OF THE COMPANY FROM LEAD_MASTER TABLE TO CREATE AN AS COMPANY
            $sql = "SELECT lf_business_name,lmc_bi_business_number,lf_business_email,lf_zip_code,lf_telephone,lf_trading_address1 FROM leads_master WHERE lm_id = '$lm_id'";
            $leadMasterResult = mysqli_query($connect_var, $sql);

            while($data = mysqli_fetch_assoc($leadMasterResult))
            {
                ////Extracting the company name from business name
                $delimit = strpos($data['lf_business_name'],"[");
                $company_name = trim(substr($data['lf_business_name'],0,$delimit));
                $addressline1 = str_replace('%20', ' ', $data['lf_trading_address1']);
                $addressline1 = str_replace('%2C', ',', $addressline1);
                $addressArray = explode(',',$addressline1);


                $datablock['companyName'] = $company_name;
                $datablock['companyNumber'] = $data['lmc_bi_business_number'];
                $datablock['customerRef'] = "DF-$lm_id";
                $datablock['firstName'] = "";
                $datablock['lastName'] = "";
                $datablock['emailAddress'] = $data['lf_business_email'];
                $datablock['postCode'] = $data['lf_zip_code'];
                $datablock['telephone'] = $data['lf_telephone'];
                $datablock['addressLine1'] = $addressArray[0];
                $datablock['addressLine2'] = '';
                $datablock['dateOfBirth'] = '';
            }

            $response = $this->addASCompany($datablock);

            if($response['httpCode'] == 200)
            {
                /////THIS IS THE CUSTOMER REFERENCE VARIABLE
                $customerRef = trim($response['response'],'"');
                $sql = "SELECT count(*) as count FROM tblAccountScoreData WHERE lm_id = '$lm_id'";
                $companyCountResult = mysqli_query($connect_var,$sql);
                while($data = mysqli_fetch_assoc($companyCountResult))
                {

                    $companyCount = $data['count'];
                }


                /////INSERT A NEW ROW TO THE DB FOR A COMPANY FOR THE CORRESPONDING LM_ID
                if($companyCount == 0)
                {
                    $newCompanyInsertSql = "INSERT INTO tblAccountScoreData(lm_id,as_customerRef,update_time) VALUES ('$lm_id','$customerRef',NOW())";
                    $newCompanyInsert = mysqli_query($connect_var,$newCompanyInsertSql);
                }


                ////CREATING AN AS COMPANY WAS SUCCESFULL NOW ADDING BANK TO THE COMPANY
                if($response['response'] != "null")
                {
                    ////FETCHING THE BANK ID BASED ON THE STATEMENT UPLOADED IN BANK STATEMENT SECTION
                    $findBankIdsql = "SELECT ocr_bank_name FROM lead_ocr_data WHERE lm_id = '$lm_id'";
                    $dbBankId  = mysqli_query($connect_var, $findBankIdsql);

                    while($data = mysqli_fetch_assoc($dbBankId))
                    {
                        $tableBankId = $data['ocr_bank_name'];

                    }

                    $bankDataResponse = $this->addASBank($customerRef,$tableBankId);
                    
                    /////SUCCESSFUL IN ADDING A BANK TO THE AS COMPANY
                    if($bankDataResponse['httpCode'] == 200)
                    {
                        $bankID = intval(trim($bankDataResponse['response'],'"'));

                        $sql = "SELECT as_bankId FROM tblAccountScoreData WHERE lm_id = '$lm_id'";
                        $bankIdCountQuery = mysqli_query($connect_var, $sql);
                        $tableBankIDCounter = 0;
                        while($data = mysqli_fetch_assoc($bankIdCountQuery))
                        {
                         $tableBankID[$tableBankIDCounter] = $data['as_bankId'];
                            $tableBankIDCounter++;
                        }


                        ////UPDATING THE COMPANY WITH A BANKID IN DB BCZ AT FIRST THE COMPANY WONT HAVE ANY BANKID INSERTED,SO UPDATING ON THE SAME ROW
                        if($tableBankID[0] == "")
                        {
                            $updateTableWithBankIdSql = "UPDATE tblAccountScoreData SET as_bankId = '$bankID',update_time = NOW() WHERE lm_id = '$lm_id'";
                            $updateTableWithBankIdQuery = mysqli_query($connect_var, $updateTableWithBankIdSql);
                        }
                        ////INSERTING A NEW BANK ID IF THE BANKID IS NOT PRESENT IN THE DB FOR CORRESPONDING LM_ID
                        elseif(isset($tableBankID))
                        {
                            if(!in_array($bankID,$tableBankID))
                            {
                                $oldCompanyWithNewBankSql = "INSERT INTO tblAccountScoreData(lm_id,as_customerRef,as_bankId,update_time) VALUES ('$lm_id','$customerRef',$bankID,NOW())";
                                $oldCompanyWithNewBankQuery = mysqli_query($connect_var,$oldCompanyWithNewBankSql);
                            }
                        }

                        //////ADDING AS ACCOUNT TO THE AS BANK FOR THE AS COMPANY

                        $accountAddedResponse = $this->addASAccount($customerRef,$bankID,$ocrAccountBlock);

                        if($accountAddedResponse['httpCode'] == 200 && isset($accountAddedResponse['response']))
                        {
                            /////AS ACCOUNT ADDED SUCCESSFULY TO THE LM_ID
                            $accountRef = trim($accountAddedResponse['response'],'"');

                            $accountUpdateSql = "UPDATE tblAccountScoreData SET as_accountRef = '".$accountRef."',as_account_id = '".$ocrAccountBlock['accountId']."',as_account_name = '".$ocrAccountBlock['accountName']."',as_sort_code = '".$ocrAccountBlock['sortCode']."',as_account_number = '".$ocrAccountBlock['accountNumber']."' WHERE lm_id = '$lm_id' AND as_bankId = '".$bankID."'";
                            $accountQuery = mysqli_query($connect_var,$accountUpdateSql);

                            //////ADDING TRANSACTIONS TO THE ACCOUNT
                            $transactionAddedResponse = $this->AddASTransaction($bankID,$accountRef,$customerRef,$finalTransactionBlock);

                            ////SUCCESSFULY ADDED TRANSACTIONS TO THE ACCOUNT
                            if($transactionAddedResponse['httpCode'] == 200 && trim($transactionAddedResponse['response'],'"') == 'Transactions Added')
                            {
                                /////COMPLETION OF THE IMPORT STARTS

                                $importCompletionResponse = $this->completeImportProcess($ocrAccountBlock['accountId'],$bankID,$customerRef,$requestStartRange,$requestEndRange);
                                if($importCompletionResponse['httpCode'] == 200)
                                {
                                    $message = "IMPORT SUCCESSFUL";
                                    return array('success' => 'TRUE', 'code' => '200','message' => $message);
                                }
                            }
                            ////COULD NOT UPLOAD TRANSACTIONS TO THE ACCOUNT
                            elseif ($transactionAddedResponse['httpCode'] == 500)
                            {
                                $message = "Could not ADD Transactions to the Bank account";
                                return array('success' => 'false', 'code' => '200','message' => $message);
                            }




                        }
                        else
                        {
                            $message = "could not add new account";
                            return array('Status code'=>500,'Message'=>$message);
                        }

                    }
                    else
                    {
                        $message = "Error in adding a Bank to the Company";
                        return array('success' => 'false', 'code' => '200','message' => $message);
                    }



                }
                elseif($response['response'] === "null")
                {
                    $message = "Could not create an account because the Customer Reference is blank";
                    return array('success' => 'false', 'code' => '200','message' => $message);
                }

            }
        }
        else
        {
            $message = "Please Upload the bank statements and retry it once again";
            return array('success' => 'false', 'code' => '200','message' => $message);
        }
    }


    /**
     * Login to get the auth token
     */
    private function login()
    {
        $uri = 'v1/authenticate';

        $response = $this->asDataApi($uri, 'POST', json_encode($this->credentials));

        if ($response['success'] === 'true' && isset($response['authToken'])) {

            $_SESSION['ocr_auth_token'] = 'Bearer ' . $response['authToken'];

            $this->header[] = 'Authorization:' . $_SESSION['ocr_auth_token'];
        }

    }


    private function addASBank($customerRef,$bankID)
    {
        $addBankUri = "v1/enrich/addBank";

        $bankDataBlock['customerRef'] = $customerRef;
        $bankDataBlock['bankId'] = $bankID;

        ////AS ENRICH BANK API WONT WORK IF BANK_ID OR CUSTOMER REF IS BLANK OR 0 OR NULL
        if($bankDataBlock['bankId'] == 0)
        {
            $message = "Bank ID cannot be 0";
            return array('httpCode'=>500,'Message'=>$message);
        }
        elseif (!isset($bankDataBlock['customerRef']) || $bankDataBlock['customerRef'] == '' || $bankDataBlock['customerRef'] == null)
        {
            $message = "customerRef cannot be  blank";
            return array('httpCode'=>500,'Message'=>$message);
        }

        $bankDatajsonBlock = json_encode($bankDataBlock,JSON_FORCE_OBJECT);
        $bankDataResponse = $this->createAccountCurl($addBankUri, 'POST', false, $bankDatajsonBlock);
        return $bankDataResponse;
    }

    private function addASAccount($customerRef,$bankID,$ocrAccountBlock)
    {
        $addAccountUri = "v1/enrich/addAccount";

        $accountDataBlock['customerRef'] = $customerRef;
        $accountDataBlock['bankId'] = $bankID;
        $accountDataBlock['accountId'] = $ocrAccountBlock['accountId'];
        $accountDataBlock['accountName'] = $ocrAccountBlock['accountName'];
        $accountDataBlock['sortCode'] = $ocrAccountBlock['sortCode'];
        $accountDataBlock['accountNumber'] = $ocrAccountBlock['accountNumber'];
        $accountDataBlock['currentBalance'] = $ocrAccountBlock['currentBalance'];
        $accountDataBlock['availableBalance'] = $ocrAccountBlock['availableBalance'];

        $accountDatajsonBlock = json_encode($accountDataBlock,JSON_FORCE_OBJECT);
        $accountDataResponse = $this->createAccountCurl($addAccountUri, 'POST', false, $accountDatajsonBlock);
        return $accountDataResponse;
    }

    private function AddASTransaction($bankID,$accountRef,$customerRef,$tableTransactionBlock)
    {

        $addTransactionUri = "v1/enrich/addTransactions";

        $transactionDataBlock['bankId'] = $bankID;
        $transactionDataBlock['accountRef'] = $accountRef;
        $transactionDataBlock['customerRef'] = $customerRef;
        $transactionDataBlock['transactions'] = $tableTransactionBlock;

        $transactionDatajsonBlock = json_encode($transactionDataBlock);
        $transactionDataResponse = $this->createAccountCurl($addTransactionUri, 'POST', false, $transactionDatajsonBlock);

        return $transactionDataResponse;

    }

    private function completeImportProcess($accountId,$bankID,$customerRef,$requestStartRange,$requestEndRange)
    {
        $completeImportUri = "v1/enrich/completeImport";

        $completeImportDataBlock['accountId'] = $accountId;
        $completeImportDataBlock['bankId'] = $bankID;
        $completeImportDataBlock['requestStartRange'] = $requestStartRange;
        $completeImportDataBlock['requestEndRange'] = $requestEndRange;
        $completeImportDataBlock['customerRef'] = $customerRef;

        $completeImportDatajsonBlock = json_encode($completeImportDataBlock,JSON_FORCE_OBJECT);
        $completeImportDataResponse = $this->createAccountCurl($completeImportUri, 'POST', false, $completeImportDatajsonBlock);



        if($completeImportDataResponse['httpCode'] == 200 && isset($completeImportDataResponse['response']))
        {
            $completeImportReference = trim($completeImportDataResponse['response'],'"');

            ////TRACKING THE PROGRESS OF THE PROCESS
            $trackProgressUri = "v1/enrich/trackProgress";
            $trackProgressDataBlock['completeImportReference'] = $completeImportReference;
            $trackProgressDatajsonBlock = json_encode($trackProgressDataBlock,JSON_FORCE_OBJECT);
            $trackProgressDataResponse = $this->createAccountCurl($trackProgressUri, 'POST', false, $trackProgressDatajsonBlock);

            return $trackProgressDataResponse;
        }

    }

    public function getASClientId($as_customerRef)
    {

        $asClientIDurl = "v1/customerId?request.externalReference=$as_customerRef";
        $clientIDResponse = $this->createAccountCurl($asClientIDurl, 'GET', false,'');

        if($clientIDResponse['httpCode'] == 200)
        {
            $AsClientId = $clientIDResponse['response'];
            $x = (explode(":",$AsClientId));
            $AsClientId = str_replace('"',"",$x[1]);
            $AsClientId = str_replace('}',"",$AsClientId);
        }
        return $AsClientId;
    }

    public function addASCompany($datablock)
    {
        $addCompanyUri = "v1/enrich/createCompany";
        $datablock = json_encode($datablock,JSON_FORCE_OBJECT);
        $addCompanyResponse = $this->createAccountCurl($addCompanyUri, 'POST', false, $datablock);
        return $addCompanyResponse;
    }

    
    public function checkConsentStatus($customerId){
        $uri = "v1/customer/".$customerId."/getCompleteCustomerProgressHistory";
        $customerIdHistoryResponse = $this->createAccountCurl($uri, 'GET', false);
        return $customerIdHistoryResponse;
    }


    public function getCustomerIdFromReferenceId($customerRefId){
        $uri = "v1/customerId/?request.externalReference=".$customerRefId;
        $customerIdResponse = $this->createAccountCurl($uri, 'GET', false);
        return $customerIdResponse;
    }


    public function getFullData($customerId){
        $uri = "v1/customer/".$customerId."/allData";
        $fullDataResponse = $this->createAccountCurl($uri, 'GET', false);
        return $fullDataResponse;
    }


    public function getCustomerAccount($customerId){
        $uri = "v1.3/customer/".$customerId."/customerAccounts";
        $customerAccountsResponse = $this->createAccountCurl($uri, 'GET', false);
        return $customerAccountsResponse;
    }


    public function getPDFLink($customerId, $startMonth='',$startYear='', $endMonth='', $endYear=''){
        $uri = "v1/pdf/".$customerId."/getLatestPdfUrl";
        if($startMonth!=''){
            $uri.='?request.customerId='.$customerId.'&request.startYear='.$startYear.'&request.startMonth='.$startMonth.'&request.endYear='.$endYear.'&request.endMonth='.$endMonth;
        }
        $customerAccountsResponse = $this->createAccountCurl($uri, 'GET', false);
        return $customerAccountsResponse;
    }

    

}



