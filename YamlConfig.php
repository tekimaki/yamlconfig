<?php 
/*
a simple class of static methods to return some basic configuration data or import it

forward looking it would be preferable if packages exposed methods to get data and 
this class could get those methods and call on them, rather than having to have sepecific functions 
in here
*/

class YamlConfig {
	private $mInstance;

	private function YamlConfig(){
	}

	public function getInstance(){
		if( empty( $this->mInstance ) ){
			$this->mInstance = new YamlConfig();
		}
		return $this->mInstance;
	}

	public static function processUploadFile( &$pParamHash ){ 
		if( YamlConfig::verifyUpload( $pParamHash ) ){
			foreach( $pParamHash['upload_process'] as $file ){
				if( $hash = Horde_Yaml::loadFile( $file['tmp_name'] ) ){
					// parser is a little annoying when it comes to n and y - it reinterprets them as FALSE and TRUE
					// we're lazy and dont want to regex the dump so lets try just flipping them back
					foreach( $hash['kernel_config'] as $pkg=>$data ){
						foreach( $hash['kernel_config'][$pkg] as $config=>$value ){
							if( $value === TRUE || $value === FALSE ){
								$hash['kernel_config'][$pkg][$config] = $value?'y':'n';	
							}
						}
					}
					$pParamHash['config_data'] = $hash;

					// store the configurations
					YamlConfig::setKernelConfig( $pParamHash ); 
				}
			}
		}
		else{
			$pParamHash['config_log']['ERRORS'] .= "Upload verification failed. ".$pParamHash['errors']['files'];
		}

		return ( 
			empty( $pParamHash['errors'] ) || 
			count( $pParamHash['errors'] ) == 0 
		);
	}

	private function verifyUpload( &$pParamHash ){
		if( !empty( $_FILES )) {
			foreach( $_FILES as $key => $file ) {
				if( !empty( $file['name'] ) && !empty( $file['tmp_name'] ) && is_uploaded_file( $file['tmp_name'] ) && empty( $file['error'] ) ) {
					$pParamHash['upload_process'][$key] = $file;
				}
			}
		}else{
			$pParamHash['errors']['files'] = tra( '$_FILES is empty' );
		}
		return ( 
			empty( $pParamHash['errors'] ) || 
			count( $pParamHash['errors'] ) == 0 
		);
	}

	// data from kernel_config table by package
	public static function getKernelConfig( $pPackage ){ 
		global $gBitSystem;

		$data = array( 'kernel_config'=>array() );
		$pkgs = array();

		if( !empty( $pPackage ) && strtoupper( $pPackage ) != 'ALL' ){
			if( in_array( $pPackage, array_keys($gBitSystem->mPackages) ) ){
				$pkgs[$pPackage] = "y";
			}
			else{
				$pParamHash['errors']['package'] = tra( 'Package not in system' );
			}
		}else{
			$pkgs = &$gBitSystem->mPackages;
		}

		foreach( $pkgs as $pkg=>$hash ){
			// hideous - but gBitSystem currently has no other way to return config by package name
			$gBitSystem->mConfig = NULL;
			$gBitSystem->loadConfig( $pkg );
			ksort( $gBitSystem->mConfig );
			$data['kernel_config'][$pkg] = $gBitSystem->mConfig; 
		}
		ksort( $data );

		// restore normal settings
		$gBitSystem->mConfig = NULL;
		$gBitSystem->loadConfig();

		$ret = Horde_Yaml::dump( $data );

		return $ret;
	}

	// data from themes layouts
	public static function getLayouts( $pPackage ){
		$layouts = $gBitThemes->getAllLayouts();
		$ret = $layouts;
		return $ret;
	}

	public static function setKernelConfig( &$pParamHash ){
		global $gBitSystem;
		if( !empty( $pParamHash['config_data'] ) && !empty( $pParamHash['config_data']['kernel_config'] ) ){
			$hash = $pParamHash['config_data'];

			foreach( $hash['kernel_config'] as $pkg=>$data ){
				foreach( $hash['kernel_config'][$pkg] as $config=>$value ){
					$gBitSystem->storeConfig( $config, $value, $pkg );
					$pParamHash['config_log']['KERNEL::storeConfig'][$pkg][$config] = $value;
				}
			}
		}
	}
}
