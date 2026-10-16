<?php
// Keep PHP sessions and Flash resources on one local origin.
if (isset($_SERVER['HTTP_HOST']) && strtolower($_SERVER['HTTP_HOST']) === 'localhost') {
    header('Location: http://127.0.0.1' . ($_SERVER['REQUEST_URI'] ?? '/'), true, 307);
    exit;
}

$conn                      = null;
$c_host                    = '127.0.0.1';
$config['UID']             = 'sa';
$config['PWD']             = '123456';
$config['Database']        = 'Db_Membership';
$config['CharacterSet']    = 'UTF-8';
$dbtank					   = 'Db_Tank';
$dbtank41				   = 'Db_Tank41';
$dbmembership			   = 'Db_Membership';
$RateTimeToCoin  		   = 10; // Quantidade de coins por tempo online.


#--------------------------------------------
#--------------------------------------------
#--------------------------------------------

$LinkLogin				= 'http://127.0.0.1/';
$LinkFlash				= 'http://127.0.0.1/flash/';
$jogando				= 'TankDev'; 
$titulo					= 'TankDev';
$description   			= 'Copyright © 2022 TankDev Todos os direitos reservados.';
$KeyWords				= 'DDtank,4.1,original';
$pagina					= 'http://www.facebook.com/TankDev/';
$WSDL					= 'http://127.0.0.1:2008/CenterService/?wsdl';
#--------------------------------------------
#--------------------------------------------
#--------------------------------------------


//add Functions
include('function.php');

//Add Clases
include('Models/index.php');

#--------------------------------------------
#--------------------------------------------
#--------------------------------------------

#--------------------------------------------
#--------------------------------------------
#--------------------------------------------

co();
@session_start();
#-----------------------------------------
