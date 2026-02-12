document.addEventListener("DOMContentLoaded", function() {
    // 1. Inyectar la biblioteca Matter.js dinámicamente si no existe
    if (typeof Matter === 'undefined') {
        const script = document.createElement('script');
        script.src = "https://cdnjs.cloudflare.com/ajax/libs/matter-js/0.19.0/matter.min.js";
        script.onload = () => iniciarNieve();
        document.head.appendChild(script);
    } else {
        iniciarNieve();
    }

    function iniciarNieve() {
        const { Engine, Render, Runner, Bodies, Composite, Events } = Matter;
        const engine = Engine.create();
        const world = engine.world;

        const render = Render.create({
            element: document.body,
            engine: engine,
            options: {
                width: window.innerWidth,
                height: window.innerHeight,
                wireframes: false,
                background: 'transparent'
            }
        });

        render.canvas.id = 'snow-canvas';
        
        // Función para crear copos
        const crearCopo = () => {
            const x = Math.random() * window.innerWidth;
            const copo = Bodies.circle(x, -10, Math.random() * 3 + 1, {
                friction: 0.001,
                restitution: 0.5,
                label: 'nieve',
                render: { fillStyle: window.matchMedia('(prefers-color-scheme: dark)').matches ? 'white' : '#a0aab4' }
            });
            Composite.add(world, copo);
        };

        // Limpieza para no saturar la memoria
        Events.on(engine, 'beforeUpdate', () => {
            Composite.allBodies(world).forEach(body => {
                if (body.position.y > window.innerHeight) {
                    Composite.remove(world, body);
                }
            });
        });

        setInterval(crearCopo, 100);
        Runner.run(Runner.create(), engine);
        Render.run(render);
    }
});