document.addEventListener('DOMContentLoaded', function() {
    const slides = document.querySelectorAll('.hero-slide');
    const dots = document.querySelectorAll('.slider-dot');
    let currentSlide = 0;
    const slideInterval = 5000; // Cambiar slide cada 5 segundos

    // Inicializar primer slide
    slides[0].classList.add('active');

    // Función para cambiar slide
    function goToSlide(n) {
        slides[currentSlide].classList.remove('active');
        dots[currentSlide].classList.remove('active');
        currentSlide = (n + slides.length) % slides.length;
        slides[currentSlide].classList.add('active');
        dots[currentSlide].classList.add('active');
    }

    // Auto slider
    setInterval(() => {
        goToSlide(currentSlide + 1);
    }, slideInterval);

    // Click en los dots
    dots.forEach((dot, index) => {
        dot.addEventListener('click', () => {
            goToSlide(index);
        });
    });

    // Swipe en móviles
    let touchStartX = 0;
    let touchEndX = 0;

    document.querySelector('.hero-slider').addEventListener('touchstart', e => {
        touchStartX = e.changedTouches[0].screenX;
    });

    document.querySelector('.hero-slider').addEventListener('touchend', e => {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
    });

    function handleSwipe() {
        const swipeThreshold = 50;
        const difference = touchStartX - touchEndX;

        if (Math.abs(difference) > swipeThreshold) {
            if (difference > 0) {
                // Swipe izquierda
                goToSlide(currentSlide + 1);
            } else {
                // Swipe derecha
                goToSlide(currentSlide - 1);
            }
        }
    }
});