// switching-text.js

document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".switching-text").forEach(function (container) {
        const words = container.querySelectorAll(".word");
        let currentIndex = 0;

        // Activate the first word immediately
        words[currentIndex].classList.add("active");

        function showNextWord() {
            const currentWord = words[currentIndex];
            const nextIndex = (currentIndex + 1) % words.length;
            const nextWord = words[nextIndex];

            currentWord.classList.remove("active");
            currentWord.classList.add("out");

            nextWord.classList.remove("out");
            nextWord.classList.add("active");

            currentIndex = nextIndex;
        }

        setInterval(showNextWord, 4000);
    });
});