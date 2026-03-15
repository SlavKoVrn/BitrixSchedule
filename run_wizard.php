<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

// Include wizard mechanism
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/classes/general/wizard.php");

// Initialize wizard with its unique ID
// Format: "module_id:wizard_name" or just the wizard path identifier
$wizard = new CWizard("vit:doctor"); 
$wizard->Install(); // Launches and displays the wizard

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
?>