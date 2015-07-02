<?php
/**
 * Breadcrumb NavXT - uninstall class
 *
 * uninstall class for WordPress Uninstall Plugin API
 * 
 * @see uninstall.php
 *
 * @author Tom Klingenberg
 */


/**
 * Breadcrumb NavXT abstract plugin uninstaller class
 * 
 * @author Tom Klingenberg
 */
abstract class bcn_uninstaller_abstract {

	/**
	 * plugin base
	 * 
	 * @var string plugin dirname
	 */
	protected $_base = '';
	
	/**
	 * plugin name
	 *
	 * @var string plugin basename (the php-file including the .php suffix)
	 */
	protected $_plugin = '';

	/**
	 * uninstalled flag
	 * 
	 * @var bool uninstall flag, true if uninstall allready run, false on init
	 */
	protected $_uninstalled = false;
	
	/**
	 * uninstall result
	 * 
	 * @var bool wether or not uninstall worked
	 */
	protected $_uninstallResult = null;
	
	/**
	 * get plugin path
	 * 
	 * @return string full path to plugin file
	 */
	protected function _getPluginPath()
	{
		return sprintf('%s/%s/%s', WP_PLUGIN_DIR, $this->_base, $this->_plugin);		
	}

	/**
	 * constructor 
	 * 
	 * @param  array $options class options
	 * 				plugin => 
	 */
	public function __construct(array $options = null)
	{
		/* plugin setter */				
		if (isset($options['plugin']))
		{
			$this->setPlugin($options['plugin']);
		}
		
		/* init */		
		$this->_uninstallResult = $this->uninstall();				
	}
	
	/**
	 * Result Getter
	 * 
	 * @return bool wether or not uninstall did run successfull.
	 */
	public function getResult()
	{
		return $this->_uninstallResult;	
	}
	
	/**
	 * plugin setter
	 * 
	 * @param  string $plugin plugin name as common with wordpress as 'dir/file.php' 
	 * 				          e.g. 'breadcrumb-navxt/breadcrumb_navxt_admin.php'.
	 * @return this 
	 */
	public function setPlugin($plugin)
	{
		/* if plugin contains a base, check and process it. */		
		if (false !== strpos($plugin, '/'))
		{
			// check
			
			$compare = $this->_base . '/';
			
			if (substr($plugin, 0, strlen($compare)) != $compare)
			{
				throw new DomainException(sprintf('Plugin "%s" has the wrong base to fit the one of Uninstaller ("%").', $plugin, $this->_base), 30001);
			}
			
			// process
			
			$plugin = substr($plugin, strlen($compare));
		}
		
		/* set local store */
		
		$this->_plugin = $plugin;
		
		return $this;
	}

} /// class bcn_uninstaller_abstract

/**
 * Breadcrumb NavXT uninstaller class
 * 
 * @author Tom Klingenberg
 */
class bcn_uninstaller extends bcn_uninstaller_abstract {

	/**
	 * plugin base
	 * 
	 * @var string plugin dirname
	 */
	protected $_base = 'breadcrumb-navxt';
	
	/**
	 * uninstall breadcrumb navxt admin plugin
	 * 
	 * @return bool
	 */
	private function _uninstallAdmin()
	{	
		// load dependencies if applicable
		
		if(!class_exists('bcn_admin'))									
			require_once($this->_getPluginPath());
			
		// uninstall		
		$bcn_admin->uninstall();
	}	
	
	/**
	 * uninstall method
	 * 
	 * @return bool wether or not uninstall did run successfull.
	 */
	public function uninstall()
	{
		if ($this->_uninstalled)
		{
			throw new BadMethodCallException('Uninstall already exectuted. It can be executed only once.', 30101);
		}
		
		// decide what to do
		switch($this->_plugin)
		{
			case 'breadcrumb_navxt_admin.php':
				return $this->_uninstallAdmin();
				
			case 'breadcrumb_navxt_class.php':
				return true;
															
			default:
				throw new BadMethodCallException(sprintf('Invalid Plugin ("%s") in %s::uninstall().', $this->_plugin , get_class($this)), 30102);				
		}
		
		// flag object as uninstalled
				
		$this->_uninstalled = true;		
	}
	
} /// class bcn_uninstaller
