<?php
class MemberData{
    Public $AppID;
    Public $UserID;
    Public $UserName;
    Public $LowerName;
    Public $MobileAlias;
    Public $IsAnonymous;
    Public $LastActivityDate;
    Public $LoginDate;
    Public $Point;
    Public $Password;
    Public $PassEncrypted;
    Public $Email;
    Public $CreateDate;
    Public $GameID;
    Public $NickName;
    //Data Pessoal Do usuario
    
    function __construct($UserName,$Password) {
        if(isset($UserName) && isset($Password))
        {
        $this->UserName = $UserName;
        $this->NickName = $UserName;
        $this->LowerName = strtolower($UserName);
        $this->Password = $Password;
        $this->PassEncrypted = strtoupper(md5($Password));
        }
    }


    Public function isAuthenticated()
    {
        global $conn;
        $applicationName = 'DanDanTang';
        $id=0;
        $data = array(
            array($applicationName, SQLSRV_PARAM_IN),
            array($this->UserName, SQLSRV_PARAM_IN),
            array($this->PassEncrypted, SQLSRV_PARAM_IN),
            array(&$id, SQLSRV_PARAM_OUT)
        );
        $retun = sqlsrv_prepare($conn,"{call Mem_Users_Accede (?,?,?,?)}",$data);
        if( $retun ) {
       }else{
            echo "Algum Erro Ocorreu por favor contate um Administrador.<br />";
            die( print_r( sqlsrv_errors(), true));
       }
        if(!sqlsrv_execute($retun))
        {
            die( print_r( sqlsrv_errors(), true));
        }
        sqlsrv_next_result($retun);
        $this->UserID = $id;
        if($this->UserID >= 0 && isset($id))
        {
            return true;
        }
        else
        {
            return false;
        }
        return false;
    }

    public function isUserIDValid()
    {
        // Valido = existe um GameID real (preenchido por GameData()) e != -1.
        return isset($this->GameID) && $this->GameID !== null && (int)$this->GameID != -1;
    }

    Public function GameData()
    {
        global $conn,$dbtank41;
        $str = "select NickName,UserID from {$dbtank41}.dbo.Sys_Users_Detail where UserName = ?";
        $smtp = sqlsrv_query($conn,$str,array($this->LowerName),array("Scrollable"=>SQLSRV_CURSOR_KEYSET));
        $data = sqlsrv_fetch_array($smtp,SQLSRV_FETCH_ASSOC);

        if(isset($data["NickName"]))
        {
            $this->GameID = $data["UserID"];
            $this->NickName = $data["NickName"];
        }
    }

    public function GetByID($UserID)
    {
        global $conn,$dbtank41;
        $str = "select UserName,NickName from {$dbtank41}.dbo.Sys_Users_Detail where UserID = ?";
        $smtp = sqlsrv_query($conn,$str,array((int)$UserID),array("Scrollable"=>SQLSRV_CURSOR_KEYSET));
        $data = sqlsrv_fetch_array($smtp,SQLSRV_FETCH_ASSOC);

        if(isset($data["NickName"]))
        {
            $this->UserName = $data["UserName"];
            $this->NickName = $data["NickName"];
        }
    }

    Public function SendRecharge($chargeID,$Money,$NeedMoney)
    {
    global $conn,$dbtank41,$WSDL;
	$hoje = date('d/m/Y');
	$str = "insert into {$dbtank41}.[dbo].[Charge_Money] values(?,?,?,?,1,'PicPay',?,null,?)";
	$data = sqlsrv_prepare($conn,$str,array($chargeID,$this->UserName,$Money,$hoje,$NeedMoney,$this->NickName));
	if(!$data)
	{
		die( print_r( sqlsrv_errors(), true));
	}
	$result = sqlsrv_execute($data);
	if(!$result)
	{
		die( print_r( sqlsrv_errors(), true));
    }
    $options = array(
        'soap_version'=> SOAP_1_1, 
       'exceptions'=> true, 
        'trace'=> 1,
        'cache_wsdl'=> 0,
    );
      $client = new SoapClient($WSDL, $options);
      if($client)
      {
      $obj = array('userID' => $this->GameID ,"chargeID"=>$chargeID);
      $client->ChargeMoney($obj);
      echo Alert($this->GameID);
      echo Alert($chargeID);
      }
    }



}
?>