<?php

/*
* Script without interest out of his context, just a sample of
* dream factory cutom script
* Receive a file, save it as binary and save a row on a bdd using
* Gnieark 2016 https://blog-du-grouik.tinad.fr
*/

$verb = $event['request']['method'];


if($verb !== 'POST'){
    throw new \DreamFactory\Core\Exceptions\BadRequestException('Only POST is allowed on this endpoint.');
}
 
//checkEventResource
$fileName = urldecode($event['resource']);
 
if(!preg_match('/^(.*)\.(.*)$/',$fileName)){
                throw new \DreamFactory\Core\Exceptions\BadRequestException('l\'url de la requete doit etre forme de la maniere suivante URL/{file.extension}');           
}
//check content

$contentArray = json_decode($event['request']['content'],true);

//return json_encode($contentArray);
if(($contentArray === false) || (is_null($contentArray))){
                throw new \DreamFactory\Core\Exceptions\BadRequestException('Le contenu n\'est pas au format JSON. '.var_dump($event['request']['content']));
}


$inputValues = $contentArray['resource'][0];
//vérifier la présence des clefs

if( ((!isset($inputValues['id'])) || (!isset($inputValues['id_participant'])) ||  !(isset($inputValues['data']))) && ((!isset($inputValues['id_de_correspondance'])) || (!isset($inputValues['id_participant'])) || !(isset($inputValues['data']))) ){        
        throw new \DreamFactory\Core\Exceptions\BadRequestException('Un paramètre est manquant dans le JSON.');              
}
                              
//le fichier:
$fileContent = base64_decode($inputValues['data']);
$fileNameOnStore = sha1($fileContent);
$subPath = substr($fileNameOnStore, 0, 2);

//le candidat
$res_id = $inputValues['id'];

 
//test if path already exists
 
$result0 = $platform['api']->get->__invoke('files/odooFileStore/?include_properties=false&include_folders=true&include_files=false&full_tree=false&zip=false');
if(substr($result0['status_code'],0,1) <> 2){
     throw new \DreamFactory\Core\Exceptions\BadRequestException('Un probleme est survenu avec l\'espace de stockage');
    
}

$pathsList = $result0['content']['resource'];
$pathExists = false;
foreach($pathsList as $path){
    if(($path['name'] == $subPath) && ($path['type'] == "folder")){
        $pathExists = true;
        break;
    }
}


if(!$pathExists){
    //créer le repertoire    
    $str = '{"resource":[{"name":"'.$subPath.'","type":"folder"}]}';
    $r = $platform['api']->post->__invoke('files/odooFileStore/?extract=false&clean=false&check_exist=false',$str);
}

//Mettre le binaire
$r1 = $platform['api']->post-> __invoke('files/odooFileStore/'.$subPath.'/'.$fileNameOnStore,  $fileContent);

if(substr($r1['status_code'],0,1) <> 2){
     throw new \DreamFactory\Core\Exceptions\BadRequestException('Un probleme est survenu lors de l\'enregistrement du fichier');
}

//enregistrer le fichier dans la base de données
$newRow = array('resource'      => array(
  "create_uid"          => 1,
  "res_id"              => $res_id,
  "res_name"            => "piece jointe candidature",
  "res_model"           => "res.partner",
  "type"                => "binary",
  "datas_fname"         => $fileName,
  "file_size"           => strlen($event['request']['content']),
  "name"                => $fileName,
  "company_id"          => 1,
  "write_uid"           => 1,
  "store_fname"         => $subPath."/".$fileNameOnStore
  )
);
 
$data_string = '{"resource":['.json_encode($newRow).']}';
 
$result2 = $platform['api']->post-> __invoke('odoocloneprod/_table/ir_attachment',$newRow);

if(substr($result2['status_code'],0,1) <> 2){
     throw new \DreamFactory\Core\Exceptions\BadRequestException('Un probleme lors de l\'enregistrement du fichier en vbase de donnée');
}
$event['response'] = array(
    'status_sode'   => 200,
    'content'       => 'ok'
);
 

return;
