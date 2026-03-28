# SENA Tracker - ADSO

¡Pilas pues! Este es el sistema de gestión y seguimiento de evidencias curriculares para las fichas del SENA (Enfocado en el programa ADSO). 

Diseñado con una arquitectura robusta orientada a **Front Controller** y **Módulos Independientes**, nada de código espagueti. Aquí se hacen las cosas bien, como todo un profesional. 

## 🚀 Requisitos Previos

Para correr esta vuelta sin dolores de cabeza, asegúrate de tener:
- **XAMPP / Laragon / MAMP** (Cualquier entorno con Apache y PHP >= 8.1)
- **MariaDB / MySQL** (Viene con XAMPP)
- **Node.js** (Opcional, pero recomendado si le vas a meter mano a los estilos de TailwindCSS)

## 🛠️ Instrucciones de Instalación y Ejecución

1. **Clonar el Repositorio**
   Ubícate en la carpeta pública de tu servidor (`htdocs` en XAMPP o `www` en Laragon) y clona el proyecto:
   ```bash
   git clone <url-del-repo> sena_tracker
   cd sena_tracker
   ```

2. **Levantar la Base de Datos**
   - Entra a phpMyAdmin (`http://localhost/phpmyadmin`).
   - Crea una base de datos vacía llamada `sena_tracker`.
   - Importa el archivo `database/schema.sql`. Este script ya tiene todas las tablas normalizadas (`aprendices`, `evidencias`, `calificaciones`, `instructor`) con sus llaves foráneas bien configuradas.

3. **Configurar la Conexión (Opcional)**
   Si tu usuario de MySQL no es `root` o le tienes clave, ajsuta las credenciales en el archivo de conexión:
   `config/database.php`

4. **Kompilar los estilos de Tailwind (Solo si vas a modificar el diseño)**
   El proyecto usa Tailwind CLI para no depender del CDN en producción (¡Pilas con mandar CDNs a producción!).
   ```bash
   npm install
   npm run dev
   ```
   *Nota: El CSS ya compilado vive en `assets/css/output.css`, así que si solo vas a ver el sistema, no necesitas Node.*

5. **¡A camellar!**
   Abre tu navegador y entra a:
   `http://localhost/sena_tracker/`

## 🏗️ Arquitectura del Proyecto

Este no es un cursito básico, la estructura está pensada para ser escalable:
- `index.php`: Funciona como un **Front Controller**. Atrapa todas las peticiones, gestiona el layout global y permite inyecciones limpias por AJAX.
- `config/database.php`: Conexión PDO segura. Usamos prepared statements nativos para evitar inyecciones SQL. 
- `views/modules/`: Cada vista es un módulo independiente que encapsula su propia lógica CRUD.
  - `aprendices.php`: Gestión de matrícula.
  - `evidencias.php`: Sistema de pensum aislado por fichas con opción de clonado.
  - `calificaciones.php`: Guardado en bloque (Bulk Update) con UPSERTs para optimizar peticiones.
  - `reportes.php`: Expediente académico y sabanas de notas.

## ⚠️ Buenas Prácticas y Reglas
- **Cero emulación de Prepares:** Por seguridad, usamos `PDO::ATTR_EMULATE_PREPARES => false`. Si haces un query nuevo, recuerda no repetir nombres de parámetros en el array.
- **Tailwind Configurado:** Todo lo visual usa utilidades predefinidas. No metas CSS en línea a menos que sea estrictamente para animaciones calculadas dinámicamente.

¡Hágale pues, a echar código limpio!
