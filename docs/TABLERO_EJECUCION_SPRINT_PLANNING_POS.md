# Tablero de Ejecucion - Sprint Planning POS

Fecha: 2026-04-03
Proyecto: Replica Valery/prueba.grupohmvenezuela.com

## 1) Objetivo del tablero
Usar este documento como plantilla operativa para planificar, ejecutar y cerrar cada sprint del roadmap de 18 mejoras POS.

## 2) Roles y responsabilidades sugeridas
- Product Owner (PO): prioriza historias, valida criterios de aceptacion, define alcance del sprint.
- Tech Lead: define estrategia tecnica, dependencias, revisiones de arquitectura.
- Backend Dev: migraciones, controladores, utilidades transaccionales, integraciones.
- Frontend Dev: vistas Blade, JS POS, UX multipago y formularios.
- QA: plan de pruebas funcionales, regresion, resiliencia y autorizacion por sede.
- DevOps/Soporte: feature flags, despliegue gradual, monitoreo y rollback.

## 3) Capacidad por sprint (plantilla)
Completar al inicio de cada sprint.

| Sprint | Duracion | Backend pts | Frontend pts | QA pts | Total | Objetivo principal |
|---|---|---:|---:|---:|---:|---|
| S1 | 2 semanas | 0 | 0 | 0 | 0 | |
| S2 | 2 semanas | 0 | 0 | 0 | 0 | |
| S3 | 2 semanas | 0 | 0 | 0 | 0 | |
| S4 | 2 semanas | 0 | 0 | 0 | 0 | |
| S5 | 2 semanas | 0 | 0 | 0 | 0 | |
| S6 | 2 semanas | 0 | 0 | 0 | 0 | |
| S7 | 2 semanas | 0 | 0 | 0 | 0 | |
| S8 | 2 semanas | 0 | 0 | 0 | 0 | |

## 4) Definicion de Ready (DoR)
Una historia entra al sprint solo si cumple:
- Criterios de aceptacion claros en Gherkin.
- Dependencias identificadas (tecnicas y negocio).
- Impacto en archivos/modulos identificado.
- Estrategia de prueba definida.
- Sin bloqueo legal pendiente para el cambio.

## 5) Definicion de Done (DoD)
Una historia se cierra solo si:
- Codigo mergeado en rama principal de desarrollo.
- Pruebas acordadas ejecutadas y aprobadas.
- Sin regresion en caja/POS.
- Permisos por sede validados cuando aplique.
- Evidencia funcional adjunta (captura, video o registro).
- Documentacion minima actualizada.

## 6) Tablero de historias por estado (kanban en markdown)
Copiar este bloque por sprint.

### Sprint X - Kanban
#### To Do
- [ ] HIST-001 -
- [ ] HIST-002 -

#### In Progress
- [ ] HIST-003 -

#### In Review
- [ ] HIST-004 -

#### QA
- [ ] HIST-005 -

#### Done
- [ ] HIST-006 -

## 7) Plantilla de historia ejecutable
Copiar para cada historia.

### HIST-XXX - Titulo
- Epic:
- Prioridad: Alta | Media | Baja
- Estimacion: 1 | 2 | 3 | 5 | 8 | 13
- Responsable principal:
- Co-responsables:
- Dependencias:
- Riesgos:

#### Criterios de aceptacion (Gherkin)
```gherkin
Feature:
  Scenario:
    Given
    When
    Then
```

#### Archivos objetivo
- app/
- resources/
- public/js/
- database/migrations/

#### Plan de pruebas
- Unitarias:
- Integracion:
- E2E:
- Regresion:

#### Evidencia de cierre
- PR:
- Resultado QA:
- Observaciones:

## 8) Checklist diario (Daily)
- [ ] Estado del sprint vs objetivo.
- [ ] Bloqueos tecnicos activos.
- [ ] Riesgos nuevos detectados.
- [ ] Historias movidas de estado.
- [ ] Cambios de alcance aprobados por PO.

## 9) Checklist de mitad de sprint
- [ ] Revisar burn-down real vs esperado.
- [ ] Confirmar historias en riesgo de arrastre.
- [ ] Activar mitigaciones (pairing, recorte de alcance, soporte).
- [ ] Confirmar que QA ya tiene ambiente y datos de prueba.

## 10) Checklist de cierre de sprint
- [ ] Demo interna con evidencia.
- [ ] Validacion de criterios de aceptacion por PO.
- [ ] Resultado de regresion POS documentado.
- [ ] Lecciones aprendidas registradas.
- [ ] Historias no terminadas replanificadas con justificacion.

## 11) Matriz de riesgos activa
| ID | Riesgo | Probabilidad | Impacto | Senal temprana | Mitigacion | Owner | Estado |
|---|---|---|---|---|---|---|---|
| R-01 | Duplicados por reintentos | Alta | Alto | Tickets de doble factura | Idempotencia + tests replay | Tech Lead | Abierto |
| R-02 | Fuga de datos entre sedes | Media | Alto | Usuario ve datos no permitidos | Hardening de filtros y permisos | Backend | Abierto |
| R-03 | Rechazo UX multipago | Media | Medio | Quejas de cajeros | Feature flag y capacitacion | PO | Abierto |
| R-04 | Fallo n8n/WhatsApp | Media | Medio | Reintentos fallidos | Cola asinc + backoff + trazas | Backend | Abierto |

## 12) Indicadores semanales
- Velocidad (pts terminados / sprint).
- Historias arrastradas.
- Defectos encontrados en QA.
- Defectos en produccion por sprint.
- Tiempo promedio de ciclo por historia.

## 13) Calendario de ceremonias sugerido
- Lunes 09:00: Sprint Planning (90 min).
- Diario 09:00: Daily (15 min).
- Miercoles semana 1: Refinamiento (45 min).
- Jueves semana 2: Sprint Review (60 min).
- Jueves semana 2: Retrospectiva (45 min).

## 14) Uso recomendado con los otros documentos
- Roadmap y arquitectura: docs/PLAN_IMPLEMENTACION_POS_18_MEJORAS.md
- Backlog por sprint: docs/BACKLOG_SPRINTS_POS_18_MEJORAS.md
- Este tablero: docs/TABLERO_EJECUCION_SPRINT_PLANNING_POS.md

## 15) Primer arranque sugerido (accion inmediata)
1. Completar tabla de capacidad de S1 y S2.
2. Seleccionar historias de S1 desde el backlog.
3. Asignar owner por historia y fecha objetivo.
4. Iniciar tablero Kanban de S1 con estado inicial.
5. Agendar ceremonias de las proximas 2 semanas.
