document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        var section9 = findSection9ByText();
        if (section9) {
            moveAdvProductsContainer(section9);
        }
    }, 500); // Ritarda l'esecuzione del tuo script di 500 millisecondi (0.5 secondi)
});

function findSection9ByText() {
    var contentElement = document.querySelector('.post-content-text');
    var h3Elements = contentElement.querySelectorAll('h3');
    var searchText = "Sovradosaggio/Effetti indesiderati";
    var foundElement;

    h3Elements.forEach(function(element) {
        if (element.textContent === searchText) {
            foundElement = element;
        }
    });

    return foundElement;
}

function moveAdvProductsContainer(section9) {
    var advProductsContainer = document.querySelector('.adv-products-container');

    if (advProductsContainer && section9) {
        section9.parentNode.insertBefore(advProductsContainer, section9);
    }
}
