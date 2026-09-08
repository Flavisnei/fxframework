# SMTP local

Instale este exemplo com composer install. Na raiz do framework execute:

~~~powershell
php tests/integration/smtp.php
~~~

O teste usa capturador loopback descartável, sem envio externo. Credenciais e
TLS remoto não são testados. [Manual SMTP](../../packages/admin/docs/index.html#smtp).
