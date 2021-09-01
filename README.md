# School-web-back Tesis
## Centro de Desarrollo Infantil Descubrir 🚀
El proyecto School-web-back contiene la lógica de desarrollo de [School-web-front-end](https://github.com/Bryan-Farinango/School-web) mediante APIS RESTFULL desarrollado en PHP Laravel 8 con **Docker** y conexión a Mongo DB. 
### **Video demostrativo**
* enlace_youtube
## Desarrollado por ✒️
* **Bryan Farinango** - [Bryan-Farinango](https://gist.github.com/Bryan-Farinango)
* **Josselyn Vela** - [JosselynVela](https://github.com/JosselynVela)
### Pre-requisitos 📋
Es necesario cumplir con las siguientes instalaciones
* Docker
* Laravel 8
* Consola Mongo DB o Robo3T
* Framework a elección

## Despliegue 📦

Primero es necesario clonar el repositorio en el ambiente local y a continuación ejecutar los siguientes comandos para construir y levantar el contenedor de docker

```
docker-compose build
```
```
docker-compose up -d
```
Verificar que se levanto el docker correctamente.
```
docker ps
```
```
docker exec -it id_del_proceso_de_php /bin/bash
```
```
composer install
```
```
cp src/.env.example src/.env
```
```
cp src/config/database.php.example src/config/database.php
```

## Funcinalidades Principales 📌

El proyecto School web back cumple con la funcionalidad de conexión a base de datos y lógica de programación mediante api restfull de todo el proyecto en front-end.

### Parte de las rutas publicadas mediante APIs
![image](https://user-images.githubusercontent.com/38628690/131645407-3ad98283-b2e9-432a-9742-0c3810d38e37.png)

## Proyecto levantado en servidor con docker
![image](https://user-images.githubusercontent.com/38628690/131645725-f3ea4026-f939-4d47-8ddc-d2b5c5169b5b.png)

## Ejemplo de petición por back-end postman
![image](https://user-images.githubusercontent.com/38628690/131645931-da6cb42f-b3a4-40ee-9be8-7c58d67b7d01.png)
