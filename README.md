# SENA Tracker

Sistema integral de gestión y seguimiento de evidencias curriculares para las fichas de aprendizaje del SENA (Específicamente diseñado para el programa ADSO).

Este proyecto ha sido desarrollado bajo estrictos estándares de ingeniería de software, implementando una arquitectura robusta orientada a **Front Controller** y **Módulos Independientes**. Se priorizan las buenas prácticas, la seguridad y el rendimiento del sistema sobre soluciones temporales.

## 🚀 Requisitos Previos

Para ejecutar la aplicación correctamente en un entorno de desarrollo o producción, asegúrese de contar con los siguientes componentes:

- **XAMPP / Laragon / MAMP** (Cualquier entorno de servidor local con soporte para Apache y PHP >= 8.1)
- **MariaDB / MySQL**
- **Node.js** (Opcional, pero necesario para compilar la hoja de estilos de TailwindCSS si desea realizar modificaciones visuales)

## 🛠️ Instrucciones de Instalación

1. **Clonar el Repositorio**
   Ubíquese en el directorio público de su servidor web (por ejemplo, `htdocs` en XAMPP o `www` en Laragon) y ejecute:
   ```bash
   git clone <url-del-repo> sena_tracker
   cd sena_tracker
   ```

2. **Configurar la Base de Datos**
   Importe el script SQL proporcionado (`database/schema.sql`) a través de phpMyAdmin o la consola de su gestor de bases de datos preferido. Este archivo contiene la estructura normalizada completa y las relaciones foráneas necesarias para las tablas del sistema.

3. **Configurar Variables de Entorno (Importante)**
   Para proteger la configuración sensible y evitar exponer credenciales en el código fuente, la aplicación utiliza variables de entorno dinámicas. 
   Copie el archivo de plantilla `.env.example` y renómbrelo como `.env`. Luego, introduzca los datos reales de su servidor local o remoto:
   ```ini
   DB_HOST=localhost
   DB_USER=root
   DB_PASS=123456
   DB_NAME=sena_tracker
   APP_VERSION=1.0.1
   ```

4. **Compilar los Estilos de TailwindCSS (Opcional)**
   Si planea modificar el diseño de la interfaz o las plantillas, recompile las clases utilitarias desde cero. La aplicación no utiliza CDNs externos en producción para maximizar la eficiencia de carga.
   ```bash
   npm install && npm run build
   ```

5. **Lanzar la Aplicación**
   Acceda al sistema de administración general cargando la siguiente ruta en su navegador:
   `http://localhost/sena_tracker/`

## 🏗️ Arquitectura del Proyecto

El sistema ha sido estructurado meticulosamente para facilitar su manutención y la escalabilidad futura modular:
- `index.php`: Ejerce el rol de **Front Controller**. Intercepta todas las llamadas HTTP, administra la renderización de la plantilla HTML troncal del sistema o responde directamente con objetos JSON puros si detecta que la petición proviene de la interfaz asíncrona (`X-Requested-With`).
- `config/database.php`: Controlador centralizado para las conexiones mediante PDO seguro que extrae lógicamente los parámetros de conexión desde el archivo `.env`. Obliga al uso estricto de Mapeos de Parámetros y Consultas Preparadas nativas (`PDO::ATTR_EMULATE_PREPARES => false`) bloqueando permanentemente ataques de Inyección SQL.
- `views/modules/`: Componentes modulares y aislados destinados al sistema principal (Dashboard). Implementan lógica asíncrona avanzada mediante _Fetch_ de datos para la generación y maquetado de recursos nativos (como la renderización en tiempo real de exportaciones .PDF y .XLSX por el propio cliente, minimizando los requisitos de memoria hacia el servidor backend).

## 💡 Créditos y Atribuciones

El éxito arquitectónico de las herramientas y visual de este proyecto es resultado del trabajo colaborativo de desarrollo:

- **Desarrollo y Creador Principal:** William Merchan (`wmerchan@hotmail.com`). ADSO 3070123 - Marzo de 2026.
  *(Carga con la responsabilidad de la estructura conceptual base, modelado de negocio, definición de interfaces de usuario [UI/UX] y lineamiento general de los estándares y requerimientos educativos del modelo de calificación SENA).*
- **Asistencia Estructural y Arquitectura IA:** Antigravity (DeepMind).
  *(Participación como Agente/Soporte para la inyección de patrones de diseño en el desarrollo del framework en PHP; estructuración segura de endpoints modulares para Fetching; aislamiento en el control del buffer para la exportación de archivos documentales y la estandarización final de flujos con Variables de Entorno).*
