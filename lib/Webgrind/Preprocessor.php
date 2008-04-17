<?php

class Webgrind_Preprocessor{
	const FILE_FORMAT_VERSION = 5;

	const NR_FORMAT = 'V';
	const NR_SIZE = 4;

	const ENTRY_POINT = '{main}';

	private $in, $out;
	
	function __construct($inFile, $outFile){
		$this->in = fopen($inFile, 'rb');
		$this->out = fopen($outFile, 'w+b');
	}
	
	
	function parse(){
		// Make local for faster access
		$in = $this->in;
		$out = $this->out;
		
		$nextFuncNr = 0;
		$functions = array();
		$headers = array();
		
		
		// Read information into memory
		while(($line = fgets($in))){
			if(substr($line,0,3)==='fl='){
				list($function) = fscanf($in,"fn=%s");
				if(!isset($functions[$function])){
					$functions[$function] = array('filename'=>substr(trim($line),3), 'invocationCount'=>0,'nr'=>$nextFuncNr++,'count'=>0,'summedSelfCost'=>0,'summedInclusiveCost'=>0,'callInformation'=>array());
				} 
				$functions[$function]['invocationCount']++;
				// Special case for {main} - it contains summary header
				if(self::ENTRY_POINT == $function){
					fgets($in);					
					$headers[] = fgets($in);
					fgets($in);
				}
				// Cost line
				list($lnr, $cost) = fscanf($in,"%d %d");
				$functions[$function]['summedSelfCost'] += $cost;
				$functions[$function]['summedInclusiveCost'] += $cost;				
			} else if(substr($line,0,4)==='cfn=') {
				$calledFunctionName = substr(trim($line),4);
				// Skip call line
				fgets($in);
				// Cost line
				list($lnr, $cost) = fscanf($in,"%d %d");
				
				$functions[$function]['summedInclusiveCost'] += $cost;
				
				if(!isset($functions[$calledFunctionName]['callInformation'][$function.':'.$lnr]))
					$functions[$calledFunctionName]['callInformation'][$function.':'.$lnr] = array('functionNr'=>$functions[$function]['nr'],'line'=>$lnr,'callCount'=>0,'summedCallCost'=>0);
				$functions[$calledFunctionName]['callInformation'][$function.':'.$lnr]['callCount']++;
				$functions[$calledFunctionName]['callInformation'][$function.':'.$lnr]['summedCallCost'] += $cost;
				
			} else if(strpos($line,': ')!==false){
				$headers[] = $line;
			}
		}
			
				
		// Write output
		$functionCount = sizeof($functions);
		fwrite($out, pack(self::NR_FORMAT.'*', self::FILE_FORMAT_VERSION, 0, $functionCount));
		// Make room for function addresses
		fseek($out,self::NR_SIZE*$functionCount, SEEK_CUR);
		$functionAddresses = array();
		foreach($functions as $functionName => $function){
			$functionAddresses[] = ftell($out);
			$calledFromCount = sizeof($function['callInformation']);
			fwrite($out, pack(self::NR_FORMAT.'*', $function['summedSelfCost'], $function['summedInclusiveCost'], $function['invocationCount'], $calledFromCount));
			// Write call information
			foreach($function['callInformation'] as $call){
				fwrite($out, pack(self::NR_FORMAT.'*', $call['functionNr'], $call['line'], $call['callCount'], $call['summedCallCost']));
			}
			fwrite($out, $function['filename']."\n".$functionName."\n");
		}
		$headersPos = ftell($this->out);
		// Write headers
		foreach($headers as $header){
			fwrite($out,$header);
		}
		
		// Write addresses
		fseek($out,self::NR_SIZE, SEEK_SET);
		fwrite($out, pack(self::NR_FORMAT, $headersPos));
		// Skip function count
		fseek($out,self::NR_SIZE, SEEK_CUR);
		foreach($functionAddresses as $address){
			fwrite($out, pack(self::NR_FORMAT, $address));			
		}
		
	}
	
}