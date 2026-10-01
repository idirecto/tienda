# Documentación interna del proyecto (solo agentes y desarrolladores)

Este directorio contiene el **contexto de trabajo** de la plataforma multi-tienda
de `/var/www/html/tienda`. Está pensado para que un LLM/agente (o una persona con
acceso al servidor) entienda el proyecto y pueda continuarlo sin adivinar.

> **NUNCA se sirve por web.** Ver [Privacidad](#privacidad) más abajo.
> Si ves este fichero desde un navegador, es un bug de seguridad: repórtalo.

---

## Mapa de ficheros

| Fichero | Para qué sirve | Quién lo escribe |
|---|---|---|
| [`PROMPT.md`](PROMPT.md) | **La petición de trabajo.** Lo que hay que hacer ahora | **El dueño del proyecto** |
| [`PROJECT.md`](PROJECT.md) | Arquitectura, cómo funciona todo, convenciones y trampas | El agente |
| [`STATE.md`](STATE.md) | Estado actual: hecho / pendiente / siguiente paso | El agente |
| [`CHANGELOG.md`](CHANGELOG.md) | Historial de sesiones: qué se cambió y por qué | El agente |
| [`skills/`](skills/) | Agent Skill instalable (`tienda-plataforma`) | El agente |
| [`scripts/`](scripts/) | Utilidades: instalar el skill, comprobar el blindaje | El agente |

---

## Cómo trabaja el dueño del proyecto

1. Abre `PROMPT.md` y escribe **qué quieres** (en lenguaje normal, sin formato).
2. Se lo pasa al agente ("lee `.agents/PROMPT.md` y hazlo").
3. Cuando el agente termina, deja el `PROMPT.md` a `[x]` y anota el resultado.

No hace falta saber nada técnico: cuanto más concreto sea el resultado esperado
(una URL de ejemplo, una captura, "que se vea como X"), mejor trabaja el agente.

---

## Cómo trabaja el agente (protocolo)

Al empezar **cualquier** tarea sobre este proyecto:

1. **Lee `.agents/PROMPT.md`.** Es la fuente de verdad de lo que se pide.
   Si está vacío o todo marcado `[x]`, no hay nada que hacer: pregunta.
2. **Lee `.agents/PROJECT.md`** (arquitectura) y **`.agents/STATE.md`** (por dónde
   iba el trabajo). No reinventes: comprueba si ya existe algo parecido.
3. **Trabaja** siguiendo las convenciones de `PROJECT.md`.
4. **Verifica** antes de decir que está hecho:
   ```bash
   php tools/verify.php            # comprobación automática del proyecto
   ```
   y prueba las rutas reales por HTTP (ver "Verificación" en `PROJECT.md`).
5. **Actualiza** `STATE.md` y `CHANGELOG.md`, y marca el `PROMPT.md` como hecho.
6. **Commit** con un mensaje que explique el *porqué*, no solo el *qué*.

### Reglas duras

- **Nunca** tocar la base de datos central del mayorista (tablas sin prefijo
  `mt_`): es de solo lectura para este proyecto.
- **Nunca** añadir dependencias pesadas. El proyecto es MVC propio sin framework;
  `composer.json` solo admite el SDK de AWS (opcional).
- **Nunca** subir `.env`, credenciales ni claves al repositorio.
- **Nunca** dejar la documentación accesible por web (ver abajo).
- El idioma del proyecto es **español**: comentarios, mensajes de commit, UI y
  esta documentación.

---

## Privacidad

Este directorio vive dentro del repositorio (para que esté en git y lo vea
cualquiera con acceso al servidor) pero **fuera del alcance de la web**, con tres
capas independientes:

| Capa | Dónde | Qué hace |
|---|---|---|
| 1. `.htaccess` | raíz del proyecto | `RewriteRule ... [F]` sobre `.agents`, `app`, `config`, `database`, `deploy`, `storage`, `tools`, `vendor` |
| 2. `index.php` | front controller | en el servidor embebido de PHP solo se sirven ficheros de `/public` |
| 3. VirtualHost | `deploy/apache-vhost*.conf` | `<DirectoryMatch>` con `Require all denied` |

Para comprobar que sigue blindado:

```bash
bash .agents/scripts/check-privacidad.sh
```

## Cómo instalar el skill en un agente

```bash
bash .agents/scripts/install-skill.sh
```

Crea un enlace simbólico en `~/.claude/skills/tienda-plataforma` apuntando a
`.agents/skills/tienda-plataforma`, de forma que el agente lo descubra solo.
