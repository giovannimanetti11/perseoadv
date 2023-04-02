document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        var section9 = findSection9ByText();
        if (section9) {
            moveAdvProductsContainer(section9);
        }
    }, 500); 
});

function findSection9ByText() {
    var contentElement = document.querySelector('.post-content-text');
    var h3Elements = contentElement.querySelectorAll('h3');
    var foundElement;

    if (h3Elements.length > 9) { // Se ci sono almeno 10 elementi <h3>
        foundElement = h3Elements[9]; // Prende il decimo elemento (indice 9)
    }

    return foundElement;
}




function moveAdvProductsContainer(section9) {
    var advProductsContainer = document.querySelector('.adv-products-container');

    if (advProductsContainer && section9) {
        section9.parentNode.insertBefore(advProductsContainer, section9);
    }
}
