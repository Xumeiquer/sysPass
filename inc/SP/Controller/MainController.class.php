<?php
/**
 * sysPass
 *
 * @author    nuxsmin
 * @link      http://syspass.org
 * @copyright 2012-2015 Rubén Domínguez nuxsmin@syspass.org
 *
 * This file is part of sysPass.
 *
 * sysPass is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * sysPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with sysPass.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace SP\Controller;

defined('APP_ROOT') || die(_('No es posible acceder directamente a este archivo'));

use SP\Config\Config;
use SP\Core\ActionsInterface;
use SP\Core\Init;
use SP\Core\DiFactory;
use SP\Core\Template;
use SP\Html\Html;
use SP\Mgmt\PublicLinks\PublicLink;
use SP\Http\Request;
use SP\Core\Session;
use SP\Core\SessionUtil;
use SP\Core\Exceptions\SPException;
use SP\Util\Checks;
use SP\Util\Util;

/**
 * Clase encargada de mostrar el interface principal de la aplicación
 * e interfaces que requieren de un documento html completo
 *
 * @package Controller
 */
class MainController extends ControllerBase implements ActionsInterface
{
    /**
     * Constructor
     *
     * @param        $template   Template con instancia de plantilla
     * @param string $page El nombre de página para la clase del body
     * @param bool $initialize Si es una inicialización completa
     */
    public function __construct(Template $template = null, $page = '', $initialize = true)
    {
        parent::__construct($template);

        if ($initialize) {
            $this->initialize($page);
        }
    }

    /**
     * Inicializar las variables para la vista principal de la aplicación
     *
     * @param string $page Nombre de la vista
     */
    protected function initialize($page = '')
    {
        $this->view->assign('startTime', microtime());

        $this->view->addTemplate('header');
        $this->view->addTemplate('body-start');

        $this->view->assign('isInstalled', Config::getConfig()->isInstalled());
        $this->view->assign('sk', SessionUtil::getSessionKey(true));
        $this->view->assign('appInfo', Util::getAppInfo());
        $this->view->assign('appVersion', Util::getVersionString());
        $this->view->assign('isDemoMode', Checks::demoIsEnabled());
        $this->view->assign('loggedIn', Init::isLoggedIn());
        $this->view->assign('page', $page);
        $this->view->assign('icons', DiFactory::getTheme()->getIcons());
        $this->view->assign('logoIcon', Init::$WEBURI . '/imgs/logo.png');
        $this->view->assign('logoNoText', Init::$WEBURI . '/imgs/logo.svg');
        $this->view->assign('logo', Init::$WEBURI . '/imgs/logo_full.svg');
        $this->view->assign('httpsEnabled', Checks::httpsEnabled());

        // Cargar la clave pública en la sesión
        SessionUtil::loadPublicKey();

        $this->getResourcesLinks();
        $this->setResponseHeaders();
    }

    /**
     * Obtener los datos para la cabcera de la página
     */
    public function getResourcesLinks()
    {
        $jsVersionHash = md5(implode(Util::getVersion()));
        $this->view->append('jsLinks', Init::$WEBROOT . '/js/js.php?v=' . $jsVersionHash);
        $this->view->append('jsLinks', Init::$WEBROOT . '/js/js.php?g=1&v=' . $jsVersionHash);

        $themeInfo = DiFactory::getTheme()->getThemeInfo();

        if (isset($themeInfo['js'])) {
            $themeJsBase = urlencode(DiFactory::getTheme()->getThemePath() . DIRECTORY_SEPARATOR . 'js');
            $themeJsFiles = urlencode(implode(',', $themeInfo['js']));

            $this->view->append('jsLinks', Init::$WEBROOT . '/js/js.php?f=' . $themeJsFiles . '&b=' . $themeJsBase . '&v=' . $jsVersionHash);
        }

        $cssVersionHash = md5(implode(Util::getVersion()) . Checks::resultsCardsIsEnabled());
        $this->view->append('cssLinks', Init::$WEBROOT . '/css/css.php?v=' . $cssVersionHash);

        if (isset($themeInfo['css'])) {
            if (!Checks::resultsCardsIsEnabled()) {
                $themeInfo['css'][] = 'search-grid.min.css';
            }

            if (Checks::dokuWikiIsEnabled()) {
                $themeInfo['css'][] = 'styles-wiki.min.css';
            }

            $themeCssBase = urlencode(DiFactory::getTheme()->getThemePath() . DIRECTORY_SEPARATOR . 'css');
            $themeCssFiles = urlencode(implode(',', $themeInfo['css']));

            $this->view->append('cssLinks', Init::$WEBROOT . '/css/css.php?f=' . $themeCssFiles . '&b=' . $themeCssBase . '&v=' . $jsVersionHash);
        }
    }

    /**
     * Establecer las cabeceras HTTP
     */
    private function setResponseHeaders()
    {
        // UTF8 Headers
        header('Content-Type: text/html; charset=UTF-8');

        // Cache Control
        header('Cache-Control: public, no-cache, max-age=0, must-revalidate');
        header('Pragma: public; max-age=0');
    }

    /**
     * Obtener los datos para el interface principal de sysPass
     */
    public function getMain()
    {
        $this->getSessionBar();
        $this->getMenu();

        $this->view->addTemplate('footer');
        $this->view->addTemplate('body-end');
    }

    /**
     * Obtener los datos para la mostrar la barra de sesión
     */
    private function getSessionBar()
    {
        $this->view->addTemplate('sessionbar');

        $this->view->assign('adminApp', Session::getUserIsAdminApp() ? '<span title="' . _('Admin Aplicación') . '">(A+)</span>' : '');
        $this->view->assign('userId', Session::getUserId());
        $this->view->assign('userLogin', strtoupper(Session::getUserLogin()));
        $this->view->assign('userName', Session::getUserName() ?: strtoupper($this->view->userLogin));
        $this->view->assign('userGroup', Session::getUserGroupName());
        $this->view->assign('showPassIcon', !Session::getUserIsLdap());
    }

    /**
     * Obtener los datos para mostrar el menú de acciones
     */
    private function getMenu()
    {
        $this->view->addTemplate('menu');

        $this->view->assign('actions', [
            [
                'id' => self::ACTION_ACC_SEARCH,
                'title' => _('Buscar'),
                'img' => 'search.png',
                'icon' => 'search',
                'checkaccess' => 0,
                'historyReset' => 1],
            [
                'id' => self::ACTION_ACC_NEW,
                'title' => _('Nueva Cuenta'),
                'img' => 'add.png',
                'icon' => 'add',
                'checkaccess' => 1,
                'historyReset' => 0],
            [
                'id' => self::ACTION_USR,
                'title' => _('Usuarios y Accesos'),
                'img' => 'users.png',
                'icon' => 'account_box',
                'checkaccess' => 1,
                'historyReset' => 0],
            [
                'id' => self::ACTION_MGM,
                'title' => _('Elementos y Personalización'),
                'img' => 'appmgmt.png',
                'icon' => 'group_work',
                'checkaccess' => 1,
                'historyReset' => 0],
            [
                'id' => self::ACTION_CFG,
                'title' => _('Configuración'),
                'img' => 'config.png',
                'icon' => 'settings_applications',
                'checkaccess' => 1,
                'historyReset' => 1],
            [
                'id' => self::ACTION_EVL,
                'title' => _('Registro de Eventos'),
                'img' => 'log.png',
                'icon' => 'view_headline',
                'checkaccess' => 1,
                'historyReset' => 1]
        ]);
    }

    /**
     * Obtener los datos para el interface de login
     */
    public function getLogin()
    {
        $this->view->addTemplate('login');
        $this->view->addTemplate('footer');
        $this->view->addTemplate('body-end');

        $this->view->assign('demoEnabled', Checks::demoIsEnabled());
        $this->view->assign('mailEnabled', Checks::mailIsEnabled());
        $this->view->assign('isLogout', Request::analyze('logout', false, true));
        $this->view->assign('updated', Init::$UPDATED === true);
        $this->view->assign('newFeatures', array(
            _('Nuevo estilo visual basado en Material Design Lite by Google'),
            _('Usuarios en múltiples grupos'),
            _('Previsualización de imágenes'),
            _('Mostrar claves como imágenes'),
            _('Campos personalizados'),
            _('API de consultas'),
            _('Autentificación en 2 pasos'),
            _('Complejidad de generador de claves'),
            _('Consultas especiales'),
            _('Exportación a XML'),
            _('Clave maestra temporal'),
            _('Importación de cuentas desde sysPass, KeePass, KeePassX y CSV'),
            _('Optimización del código y mayor rapidez de carga'),
            _('Mejoras de seguridad en XSS e inyección SQL')
        ));

        // Comprobar y parsear los parámetros GET para pasarlos como POST en los inputs
        $this->view->assign('getParams');

        if (count($_GET) > 0) {
            foreach ($_GET as $param => $value) {
                $getParams['g_' . Html::sanitize($param)] = Html::sanitize($value);
            }

            $this->view->assign('getParams', $getParams);
        }
    }

    /**
     * Obtener los datos para el interface del instalador
     */
    public function getInstaller()
    {
        $errors = array_merge(Checks::checkPhpVersion(), Checks::checkModules());

        if (@file_exists(__FILE__ . "\0Nullbyte")) {
            $errors[] = [
                'type' => SPException::SP_WARNING,
                'description' => _('La version de PHP es vulnerable al ataque NULL Byte (CVE-2006-7243)'),
                'hint' => _('Actualice la versión de PHP para usar sysPass de forma segura')];
        }

        if (!Checks::secureRNGIsAvailable()) {
            $errors[] = [
                'type' => SPException::SP_WARNING,
                'description' => _('No se encuentra el generador de números aleatorios.'),
                'hint' => _('Sin esta función un atacante puede utilizar su cuenta al resetear la clave')];
        }

        $this->view->assign('errors', $errors);

        $this->view->addTemplate('install');
        $this->view->addTemplate('footer');
        $this->view->addTemplate('body-end');
    }

    /**
     * Obtener los datos para el interface de error
     *
     * @param bool $showLogo mostrar el logo de sysPass
     */
    public function getError($showLogo = false)
    {
        $this->view->addTemplate('error');
        $this->view->addTemplate('footer');

        $this->view->assign('showLogo', $showLogo);
    }

    /**
     * Obtener los datos para el interface de restablecimiento de clave de usuario
     */
    public function getPassReset()
    {
        if (Checks::mailIsEnabled() || Request::analyze('f', 0) === 1) {
            $this->view->addTemplate('passreset');

            $this->view->assign('action', Request::analyze('a'));
            $this->view->assign('hash', Request::analyze('h'));
            $this->view->assign('time', Request::analyze('t'));

            $this->view->assign('passReset', $this->view->action === 'passreset' && $this->view->hash && $this->view->time);
        } else {
            $this->view->assign('showLogo', true);

            $this->showError(self::ERR_UNAVAILABLE, false);
        }

        $this->view->addTemplate('footer');
        $this->view->addTemplate('body-end');
    }

    /**
     * Obtener los datos para el interface de actualización de BD
     */
    public function getUpgrade()
    {
        $this->view->addTemplate('upgrade');
        $this->view->addTemplate('footer');
        $this->view->addTemplate('body-end');

        $this->view->assign('action', Request::analyze('a'));
        $this->view->assign('time', Request::analyze('t'));
        $this->view->assign('upgrade', $this->view->action === 'upgrade');
    }

    /**
     * Obtener los datos para el interface de autentificación en 2 pasos
     */
    public function get2FA()
    {
        if (Request::analyze('f', 0) === 1) {
            $this->view->addTemplate('login-2fa');

            $this->view->assign('action', Request::analyze('a'));
            $this->view->assign('userId', Request::analyze('i'));
            $this->view->assign('time', Request::analyze('t'));
        } else {
            $this->view->assign('showLogo', true);

            $this->showError(self::ERR_UNAVAILABLE, false);
        }

        $this->view->addTemplate('footer');
        $this->view->addTemplate('body-end');
    }

    /**
     * Obtener los datos para el interface de comprobación de actualizaciones
     */
    public function getCheckUpdates()
    {
        $this->view->addTemplate('update');

        $this->view->assign('hasUpdates', false);
        $this->view->assign('hasNotices', false);

        if (Config::getConfig()->isCheckUpdates()) {
            $updates = Util::checkUpdates();

            if (is_array($updates)) {
                $description = nl2br($updates['description']);
                $version = $updates['version'];

                $this->view->assign('hasUpdates', true);
                $this->view->assign('title', $updates['title']);
                $this->view->assign('url', $updates['url']);
                $this->view->assign('description', sprintf('%s - %s <br><br>%s', _('Descargar nueva versión'), $version, $description));
            } else {
                $this->view->assign('status', $updates);
            }
        }

        if (Config::getConfig()->isChecknotices()) {
            $notices = Util::checkNotices();
            $numNotices = count($notices);
            $noticesTitle = '';

            if ($notices !== false && $numNotices > 0) {
                $noticesTitle = sprintf('%s<br><br>', _('Avisos de sysPass'));

                foreach ($notices as $notice) {
                    $noticesTitle .= sprintf('%s<br>', $notice[0]);
                }

                $this->view->assign('hasNotices', true);
            }

            $this->view->assign('numNotices', $numNotices);
            $this->view->assign('noticesTitle', $noticesTitle);
        }
    }

    /**
     * Obtener la vista para mostrar un enlace publicado
     *
     * @return bool
     * @throws \SP\Core\Exceptions\SPException
     */
    public function getPublicLink()
    {
        $hash = Request::analyze('h');

        $PublicLink = PublicLink::getItem()->getByHash($hash);

        $this->view->assign('showLogo', true);

        if (!$PublicLink
            || time() > $PublicLink->getDateExpire()
            || $PublicLink->getCountViews() >= $PublicLink->getMaxCountViews()
        ) {
            $this->showError(self::ERR_PAGE_NO_PERMISSION, false);
        } else {
            PublicLink::getItem($PublicLink)->addLinkView();

            $controller = new AccountController($this->view, null, $PublicLink->getItemId());
            $controller->getAccountFromLink($PublicLink);
        }

        $this->getSessionBar();
        $this->view->addTemplate('footer');
        $this->view->addTemplate('body-end');
    }
}