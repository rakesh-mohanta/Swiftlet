<?php
/**
 * @package Swiftlet
 * @copyright 2009 ElbertF http://elbertf.com
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU Public License
 */

class Installer extends Controller
{
	public
		$pageTitle    = 'Plugin installer',
		$dependencies = array('buffer', 'input')
		;

	function init()
	{
		$this->app->input->validate(array(
			'plugin'          => 'bool',
			'system-password' => '/^' . preg_quote($this->app->config['sysPassword'], '/') . '$/',
			'mode'            => 'string',
			'form-submit'     => 'bool',
			));

		$authenticated = isset($_SESSION['swiftlet authenticated']);

		$this->view->newPlugins       = array();
		$this->view->outdatedPlugins  = array();
		$this->view->installedPlugins = array();

		if ( isset($this->app->db) )
		{
			$requiredBy = array();

			foreach ( $this->app->plugins as $plugin )
			{
				foreach ( $this->app->{$plugin}->dependencies as $dependency )
				{
					if ( !isset($requiredBy[$dependency]) )
					{
						$requiredBy[$dependency] = array();
					}

					$requiredBy[$dependency][$plugin] = !empty($this->app->{$dependency}->ready) && $this->app->{$plugin}->version ? 1 : 0;
				}
			}

			foreach ( $this->app->plugins as $plugin )
			{
				$version = $this->app->{$plugin}->get_version();

				if ( !$version )
				{
					if ( isset($this->app->{$plugin}->hooks['install']) )
					{
						$dependencyStatus = array();

						foreach ( $plugin->info['dependencies'] as $dependency )
						{
							$dependencyStatus[$dependency] = !empty($this->app->{$dependency}->ready) ? 1 : 0;
						}

						$this->view->newPlugins[$pluginName]                      = $plugin;
						$this->view->newPlugins[$pluginName]['dependency_status'] = $dependencyStatus;
					}
				}
				else
				{
					if ( isset($this->app->{$plugin}->hooks['upgrade']) )
					{
						if ( version_compare($version, str_replace('*', '99999', $this->app->{$plugin}->upgradable['from']), '>=') && version_compare($version, str_replace('*', '99999', $this->app->{$plugin}->upgradable['to']), '<=') )
						{
							$this->view->outdatedPlugins[$plugin] = $plugin;
						}
					}

					if ( isset($this->app->{$plugin}->hooks['remove']) )
					{
						/*
						$this->view->installedPlugins[$plugin]                       = $plugin;
						$this->view->installedPlugins[$plugin]['required_by_status'] = isset($requiredBy[$plugin]) ? $requiredBy[$plugin] : array();
						*/
					}
				}
			}
		}

		ksort($this->view->newPlugins);

		if ( !$this->app->config['sysPassword'] )
		{
			$this->view->error = $this->view->t('%1$s has no value in %2$s (required).', array('<code>sysPassword</code>', '<code>/_config.php</code>'));
		}
		elseif ( empty($this->app->db->ready) )
		{
			$this->view->error = $this->view->t('No database connected (required). You may need to change the database settings in %1$s.', '<code>/_config.php</code>');
		}
		else
		{
			if ( $this->app->input->POST_valid['form-submit'] )
			{
				/*
				 * Delay the script to prevent brute-force attacks
				 */
				sleep(1);

				if ( $this->app->input->errors )
				{
					$this->view->error = $this->view->t('Incorrect system password.');
				}
				else
				{
					if ( $this->app->input->POST_raw['mode'] == 'authenticate' )
					{
						$_SESSION['swiftlet authenticated'] = TRUE;

						$authenticated = TRUE;
					}
					else if ( $authenticated && $this->app->input->POST_valid['plugin'] && is_array($this->app->input->POST_valid['plugin']) )
					{
						switch ( $this->app->input->POST_raw['mode'] )
						{
							case 'install':
								/**
								 * Create plugin versions table
								 */
								if ( !in_array($this->app->db->prefix . 'versions', $this->app->db->tables) )
								{
									$this->app->db->sql('
										CREATE TABLE `' . $this->app->db->prefix . 'versions` (
											`id`          INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
											`plugin`      VARCHAR(256)     NOT NULL,
											`version`     VARCHAR(10)      NOT NULL,
											PRIMARY KEY (`id`)
											) TYPE = INNODB
										;');
								}

								$pluginsInstalled = array();

								foreach ( $this->app->input->POST_valid['plugin'] as $pluginName => $v )
								{
									if ( isset($this->view->newPlugins[$pluginName]) && !in_array(0, $this->view->newPlugins[$pluginName]['dependency_status']) )
									{
										$this->app->plugins[$pluginName]->install();

										$this->app->db->sql('
											INSERT INTO `' . $this->app->db->prefix . 'versions` (
												`plugin`,
												`version`
												)
											VALUES (
												"' . $this->app->db->escape($pluginName)           . '",
												"' . $this->view->newPlugins[$pluginName]['version'] . '"
												)
											;');

										$pluginsInstalled[] = $pluginName;

										unset($this->view->newPlugins[$pluginName]);
									}
								}

								if ( $pluginsInstalled )
								{
									header('Location: ?notice=installed&plugins=' . implode('|', $pluginsInstalled));

									$this->app->end();
								}

								break;
							case 'upgrade':
								$pluginsUpgraded = array();

								foreach ( $this->app->input->POST_valid['plugin'] as $pluginName => $v )
								{
									if ( isset($this->view->outdatedPlugins[$pluginName]) )
									{
										$this->app->plugins[$pluginName]->upgrade();

										$this->app->db->sql('
											UPDATE `' . $this->app->db->prefix . 'versions` SET
												`version` = "' . $this->view->outdatedPlugins[$pluginName]['version'] . '"
											WHERE
												`plugin` = "' . $pluginName . '"
											LIMIT 1
											;');

										$pluginsUpgraded[] = $pluginName;

										unset($this->view->outdatedPlugins[$pluginName]);
									}
								}

								if ( $pluginsUpgraded )
								{
									header('Location: ?notice=upgraded&plugins=' . implode('|', $pluginsUpgraded));

									$this->app->end();
								}

								break;
							case 'remove':
								$pluginsRemoved = array();

								foreach ( $this->app->input->POST_valid['plugin'] as $pluginName => $v )
								{
									if ( isset($this->view->installedPlugins[$pluginName]) && !in_array(1, $this->view->installedPlugins[$pluginName]['required_by_status']) )
									{
										$this->app->db->sql('
											DELETE
											FROM `' . $this->app->db->prefix . 'versions`
											WHERE
												`plugin` = "' . $this->app->db->escape($pluginName) . '"
											LIMIT 1
											;');

										$this->app->plugins[$pluginName]->remove();

										$pluginsRemoved[] = $pluginName;

										unset($this->view->installedPlugins[$pluginName]);
									}
								}

								if ( $pluginsRemoved )
								{
									header('Location: ?notice=removed&plugins=' . implode('|', $pluginsRemoved));

									$this->app->end();
								}

								break;
						}
					}
				}
			}
		}

		if ( isset($this->app->input->GET_raw['notice']) && isset($this->app->input->GET_raw['plugins']) )
		{
			switch ( $this->app->input->GET_raw['notice'] )
			{
				case 'installed':
					$this->view->notice = $this->view->t('The following plugin(s) have been successfully installed:%1$s', '<br/><br/>' . str_replace('|', '<br/>', $this->app->input->GET_html_safe['plugins']));

					break;
				case 'upgraded':
					$this->view->notice = $this->view->t('The following plugin(s) have been successfully upgraded:%1$s', '<br/><br/>' . str_replace('|', '<br/>', $this->app->input->GET_html_safe['plugins']));

					break;
				case 'removed':
					$this->view->notice = $this->view->t('The following plugin(s) have been successfully removed:%1$s', '<br/><br/>' . str_replace('|', '<br/>', $this->app->input->GET_html_safe['plugins']));

					break;
			}
		}

		$this->view->authenticated = $authenticated;

		$this->view->load('installer.html.php');
	}
}