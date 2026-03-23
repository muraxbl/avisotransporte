# avisotransporte
Módulo para prestashop 1.7 > que permite ajustar un aviso en la página de checkout cuando no se encuentra un transportista para la zona seleccionada. Además envía un correo electrónico de alerta con los detalles del carrito y del cliente a la dirección especificada en los ajustes

## Instalación

Carga los archivos del módulo a la carpeta /modules/avisotransporte en tu instalación de prestashop. Una vez cargados, accede al backoffice -> **Módulos** -> **Module Manager** / **Catálogo de módulos** (en función de la versión de prestashop) Localiza el módulo y pulsa instalar. Alternativamente puedes utilizar la interfaz de prestashop para subir una versión comprimida del módulo y realizar todo el proceso de instalación y activación desde ahí.

## Configuración

El módulo permite configurar parámetros básicos

* Países de aplicación. Te permite restringir la activación del módulo a los países de tu elección. Si no seleccionas ninguno, se aplicará de manera general.
* Email de alertas. Puedes definir el correo electrónico que recibirá las alertas cuando un usuario se encuentre un checkout **sin métodos de envío**. Por defecto, esta configuración toma el valor de la constante PS_SHOP_EMAIL.
* Template text: Permite definir un texto que se mostrará al usuario en el paso Seleccionar transportista cuando ningún método esté disponible tras el mensaje por defecto. Por ejemplo: "Hemos recibido una alerta y habilitaremos un transportista para tí lo antes posible". *Si no se ajusta ningún texto, el usuario no verá ningún mensaje personalizado*
