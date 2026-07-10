# SISPO Enterprise ATS — Reporte de Auditoría y Refactor de Méritos (Schema Registry & UX/UI)

Este reporte técnico consolida el diagnóstico del error 404 detectado post-normalización, la implementación de la nueva arquitectura **MeritSchemaRegistry**, y la propuesta de transformación UX/UI hacia un flujo de reclutamiento de alta densidad (Enterprise ATS).

---

## 1. CAUSA RAÍZ DEL BUG 404

El error `GET /api/tipos-documento 404` se produjo tras la limpieza de controladores obsoletos en la Fase 3 del desacoplamiento de méritos:
*   **Origen del Fallo:** La página de administración `ConvocatoriasPage.vue` llamaba al endpoint `/api/tipos-documento` para alimentar el catálogo de méritos seleccionables al configurar una nueva convocatoria (para el renderizado dinámico de requisitos del afiche y baremo).
*   **Problema de Acoplamiento:** Este endpoint estaba enlazado al controlador de la tabla pivot legacy `tipos_documento` (`TipoDocumentoController`), la cual contenía lógica duplicada y redundante con el nuevo modelo de méritos normalizados. Al eliminar el controlador, la ruta dejó de existir.

---

## 2. ARQUITECTURA NUEVA: `MeritSchemaRegistry`

Para evitar reconstruir o revivir la tabla legacy de base de datos `tipos_documento`, diseñamos una solución elegante basada en **Schema Registry** en código:

### `app/Support/MeritSchemaRegistry.php`
Este registro actúa como la **Única Fuente de Verdad (SSOT)** para describir los 7 tipos de méritos normalizados aceptados por el sistema:
1.  **Formación Académica** (`formaciones_academicas`)
2.  **Formación de Posgrado** (`formaciones_postgrado`)
3.  **Experiencia Profesional** (`experiencias_profesionales`)
4.  **Experiencia en Docencia** (`experiencias_docencia`)
5.  **Capacitación y Cursos** (`capacitaciones`)
6.  **Producción Intelectual** (`producciones_intelectuales`)
7.  **Reconocimientos y Distinciones** (`reconocimientos`)

```php
// Estructura simplificada de cada Schema en el Registry:
[
    'id' => 1,
    'code' => 'FORMACION_ACADEMICA',
    'label' => 'Formación Académica',
    'table' => 'formaciones_academicas',
    'supports_multiple' => true,
    'score_category' => 'academic_formation',
    'required_documents' => [
        ['id' => 'diploma', 'label' => 'Diploma Académico'],
        ['id' => 'titulo', 'label' => 'Título en Provisión Nacional']
    ],
    'fields' => [
        ['key' => 'carrera', 'label' => 'Carrera / Programa', 'type' => 'text'],
        ['key' => 'nivel_maximo', 'label' => 'Nivel', 'type' => 'select', 'options' => [...]],
    ]
]
```

### Ventajas de esta Arquitectura:
*   **Rendimiento Extraordinario:** Eliminamos consultas SQL redundantes y pesadas uniones de base de datos para renderizar formularios en el frontend.
*   **Mantenibilidad Extrema:** Añadir un nuevo tipo de mérito o campo en el formulario de postulación ahora requiere modificar un archivo centralizado en el backend, en lugar de ejecutar complejas migraciones SQL estructuradas.
*   **Tipado e Integridad:** Los datos están fuertemente acoplados a las columnas reales de las 7 tablas normalizadas en base de datos.

---

## 3. ENDPOINTS Y RUTAS MIGRACIÓN

1.  **`GET /api/merit-schemas` (Público y Admin):**
    *   Nuevo endpoint global que devuelve el arreglo JSON generado por `MeritSchemaRegistry::all()`.
    *   Configurado como ruta pública para permitir a postulantes externos cargar el formulario de méritos y a los administradores armar las convocatorias sin requerir token JWT.
2.  **`GET /portal/tipos-documento` (Compatibilidad Backend):**
    *   Refactorizado en `PortalController@tiposDocumentoGenerales` para retornar los esquemas limpios de `MeritSchemaRegistry::all()`, garantizando compatibilidad con cualquier llamada antigua de APIs.

---

## 4. MIGRACIÓN DEL FRONTEND

Se implementaron cambios en 4 archivos clave del frontend Quasar para integrarlos con la nueva arquitectura:
*   **`ConvocatoriasPage.vue`:**
    *   Migrada la llamada inicial para consumir `/api/merit-schemas` en lugar de `/api/tipos-documento`.
    *   Actualizados los selectores y plantillas para utilizar la propiedad reactiva `.label` de los esquemas en lugar del antiguo `.nombre`.
*   **`RegistroDirectoPage.vue`:**
    *   Se actualizó para consultar `/api/merit-schemas` directamente.
    *   Se reemplazaron todas las referencias a `tipo.nombre` por `tipo.label`, permitiendo la correcta carga dinámica de secciones normalizadas.
*   **`FormMerito.vue`:**
    *   Se migró el encabezado dinámico para recibir y desplegar `tipoDocumento.label`.
*   **`stores/postulacion.js`:**
    *   Se inyectaron fallbacks en el inicializador de méritos de la postulación:
        ```javascript
        nombre: req.label || req.nombre,
        campos: req.campos || req.fields || [],
        config_archivos: req.config_archivos || req.required_documents || [],
        permite_multiples: req.permite_multiples || req.supports_multiple || false,
        ```
    *   Esto permite al portal de postulación público operar de forma transparente consumiendo esquemas tipados sin tocar el Core del motor de carga física.

---

## 5. AUDITORÍA UX/UI: DE DASHBOARD A ATS ENTERPRISE (HIGH-DENSITY)

### Diagnóstico de Deuda de Diseño
1.  **Densidad Informativa Deficiente:** Las tarjetas de las convocatorias y listados de postulaciones presentan gigantismo visual. Se visualizan pocas filas por scroll.
2.  **Scroll Infinito en Expedientes:** Para evaluar un expediente, el administrador de RRHH realiza un scroll extenso debido al espaciado inflado de cada sección de méritos.
3.  **Cuello de Botella de Clicks:** Navegar entre Convocatorias $\rightarrow$ Ver Postulantes $\rightarrow$ Abrir Expediente $\rightarrow$ Ver Calificación matemática requiere 4 redirecciones completas de página.
4.  **Baja Sensación de ATS:** Falta un ranking visual instantáneo y un estatus claro de revisión de documentos en cada postulación.

---

## 6. MOCKUP Y PROPUESTA DE NUEVA EXPERIENCIA (ATS FEEL)

Diseñamos una propuesta para transformar la vista de postulaciones a una interfaz de alta densidad inspirada en soluciones líderes de reclutamiento (ATS):

```
+---------------------------------------------------------------------------------------------------+
| SISPO ATS || Convocatorias Activas: [ 12 ] | Postulantes: [ 1,964 ] | Alerta de Riesgo: [ 3 Críticos ]  |
+---------------------------------------------------------------------------------------------------+
| FILTROS: [ Sede: Todos  v ] [ Cargo: Todos v ] [ Estado: Abiertas v ]     [ + Nueva Convocatoria ] |
+---------------------------------------------------------------------------------------------------+
| CONVOCATORIA (CÓDIGO)   | DENSIDAD (OFERTAS)   | POSTULACIONES | ESTADO  | TOP POSTULANTES PREVIEW|
+-------------------------+----------------------+---------------+---------+------------------------+
| CONV-2026-DOC-CBBA      | Cochabamba (4 Cargos)| 42 Postulantes| ABIERTA | * Perez, J. (86 pts)   |
|                         |                      |               |         | * Gomez, M. (74 pts)   |
+-------------------------+----------------------+---------------+---------+------------------------+
| CONV-2026-ADM-LPZ       | La Paz (1 Cargo)     | 18 Postulantes| CERRADA | * Choque, A. (92 pts)  |
+-------------------------+----------------------+---------------+---------+------------------------+
|
| >> [CLIC EN CONVOCATORIA] -> ABRE ATS DRAWER LATERAL (CERO REDIRECCIONES)
| +-----------------------------------------------------------------------------------------------+ |
| | CONV-2026-DOC-CBBA: DOCENCIA MEDIADA POR TIC (Cochabamba)                                     | |
| |                                                                                               | |
| |   [ KPI rápidos:  42 Enviados |  18 Evaluados |  4 En Revisión Humana |  2 Riesgo de Fraude ] | |
| |                                                                                               | |
| |   TABLA DE POSTULANTES (ALTA DENSIDAD):                                                       | |
| |   +-----------------------------------------------------------------------------------------+ | |
| |   | POSTULANTE        | SCORING MATEMÁTICO | PERFIL DOCENTE | DOCS BASE | ACCIÓN RÁPIDA     | | |
| |   +-------------------+--------------------+----------------+-----------+-------------------+ | |
| |   | PEREZ, JUAN       | 86.5 pts [Ver]     | DOCENTE TIT.   | [OK] [OK] | [Evaluar] [Firma] | | |
| |   | CHOQUE, MARIA     | 74.0 pts [Ver]     | AUXILIAR       | [!] [OK]  | [Revisar Docs]    | | |
| |   +-------------------+--------------------+----------------+-----------+-------------------+ | |
| +-----------------------------------------------------------------------------------------------+ |
```

### Plan de Mejoras UX Concretas:
1.  **Drawers en Lugar de Páginas Completas:** Al hacer clic en un postulante de la tabla principal, no se debe redirigir al usuario; se abre un **Drawer Lateral derecho** que despliega el expediente completo con navegación rápida mediante atajos de teclado para calificar al siguiente candidato inmediatamente.
2.  **KPIs Operacionales en Cabecera:** Tarjetas compactas en HSL con micro-animaciones para medir de un vistazo los postulantes activos y el estado de la revisión del afiche oficial.
3.  **Ranking Inline Dinámico:** Visualización directa de las mejores 3 puntuaciones calculadas por el Score Engine sin entrar al expediente.

---

## 7. ARCHIVOS MODIFICADOS Y ESTADO DEL BUILD

### Backend:
*   `app/Support/MeritSchemaRegistry.php` (Nuevo registro tipado)
*   `app/Http/Controllers/PortalController.php` (Refactorizado `requisitosConvocatoria` y `tiposDocumentoGenerales`)
*   `routes/api.php` (Agregado endpoint público `/api/merit-schemas`)

### Frontend:
*   `src/pages/admin/ConvocatoriasPage.vue` (Migrado al Registry y arreglado bug 404)
*   `src/pages/RegistroDirectoPage.vue` (Consumo directo de schemas)
*   `src/components/FormMerito.vue` (Refactor de títulos dinámicos)
*   `src/stores/postulacion.js` (Soporte y fallbacks para las propiedades de schemas)

### Estado del Build:
*   **Resultado:** `BUILD SUCCEEDED` (0 Errores en empaquetado Vite / SPA).
*   **Estabilidad:** Las llamadas al servidor a `/api/tipos-documento` han sido erradicadas en un 100%, eliminando por completo las alertas 404 en la consola de administración.
