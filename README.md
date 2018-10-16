# Integração Saurus x Websac


### Detalhes sobre integração Saurus x Websac
Temos o xml de recebimento de leitura vendas **saurus_leitura.xml** o saurus nos envia via webservice esse xml para ser lido, e temos tambem a carga de produtos onde mandamos o xml **saurus_carga.xml** via webservice para o saurus os exemplos estão na pasta xml:
- saurus_carga.xml
- saurus_leitura.xml

E na pasta php temos os arquivos de integração com o saurus:
- saurus-cadastro.php
- saurus-cupom.php
- saurus-retaguarda.php

Usamos no Websac as url da seguinte forma, para configuracao do WSDL no Saurus:
- http://controlware.websac.net/serv/saurus-cadastro.php
- http://controlware.websac.net/serv/saurus-cupom.php
- http://controlware.websac.net/serv/saurus-retaguarda.php

