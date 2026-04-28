(function() {
    document.querySelectorAll(".property-spotlight--carousel").forEach(function(carousel) {
        var track = carousel.querySelector(".property-spotlight__track");
        var prevBtn = carousel.querySelector(".property-spotlight__nav--prev");
        var nextBtn = carousel.querySelector(".property-spotlight__nav--next");
        var items = carousel.querySelectorAll(".property-spotlight__item");

        if (!track || !items.length) return;

        var currentIndex = 0;
        var itemWidth = items[0].offsetWidth;
        var gap = 16;
        var visibleItems = Math.floor(carousel.offsetWidth / (itemWidth + gap)) || 1;
        var maxIndex = Math.max(0, items.length - visibleItems);

        function updatePosition() {
            track.style.transform = "translateX(-" + (currentIndex * (itemWidth + gap)) + "px)";
            if (prevBtn) prevBtn.disabled = currentIndex <= 0;
            if (nextBtn) nextBtn.disabled = currentIndex >= maxIndex;
        }

        if (prevBtn) {
            prevBtn.addEventListener("click", function() {
                if (currentIndex > 0) {
                    currentIndex--;
                    updatePosition();
                }
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener("click", function() {
                if (currentIndex < maxIndex) {
                    currentIndex++;
                    updatePosition();
                }
            });
        }

        window.addEventListener("resize", function() {
            itemWidth = items[0].offsetWidth;
            visibleItems = Math.floor(carousel.offsetWidth / (itemWidth + gap)) || 1;
            maxIndex = Math.max(0, items.length - visibleItems);
            if (currentIndex > maxIndex) currentIndex = maxIndex;
            updatePosition();
        });

        updatePosition();
    });
})();
