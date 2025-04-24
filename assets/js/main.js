document.addEventListener('DOMContentLoaded', function() {
    // Auth tabs
    const authTabs = document.querySelectorAll('.auth-tab');
    const authForms = document.querySelectorAll('.auth-form');
    
    if(authTabs.length > 0) {
        authTabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const target = tab.dataset.tab;
                
                // Remove active class from all tabs and forms
                authTabs.forEach(t => t.classList.remove('active'));
                authForms.forEach(f => f.classList.remove('active'));
                
                // Add active class to clicked tab and corresponding form
                tab.classList.add('active');
                document.getElementById(target).classList.add('active');
            });
        });
    }
    
    // File upload preview
    const fileInput = document.getElementById('file');
    const filePreview = document.getElementById('file-preview');
    const thumbnailInput = document.getElementById('thumbnail');
    const thumbnailPreview = document.getElementById('thumbnail-preview');
    
    if(fileInput && filePreview) {
        fileInput.addEventListener('change', function() {
            const file = this.files[0];
            
            if(file) {
                const fileType = file.type.split('/')[0];
                
                if(fileType === 'audio') {
                    // Create audio preview
                    filePreview.innerHTML = `
                        <audio controls>
                            <source src="${URL.createObjectURL(file)}" type="${file.type}">
                            Your browser does not support the audio element.
                        </audio>
                    `;
                } else if(fileType === 'video') {
                    // Create video preview
                    filePreview.innerHTML = `
                        <video controls width="300">
                            <source src="${URL.createObjectURL(file)}" type="${file.type}">
                            Your browser does not support the video element.
                        </video>
                    `;
                } else {
                    filePreview.innerHTML = `<p>File type not supported for preview</p>`;
                }
            }
        });
    }
    
    if(thumbnailInput && thumbnailPreview) {
        thumbnailInput.addEventListener('change', function() {
            const file = this.files[0];
            
            if(file) {
                const fileType = file.type.split('/')[0];
                
                if(fileType === 'image') {
                    // Create image preview
                    thumbnailPreview.innerHTML = `
                        <img src="${URL.createObjectURL(file)}" alt="Thumbnail Preview" style="max-width: 200px; max-height: 200px;">
                    `;
                } else {
                    thumbnailPreview.innerHTML = `<p>Please select an image file</p>`;
                }
            }
        });
    }


    // Playlist and Album modals
    const playlistCards = document.querySelectorAll('.playlist-card');
    const albumCards = document.querySelectorAll('.album-card');
    const modals = document.querySelectorAll('.modal');
    
    // Open playlist modal
    playlistCards.forEach(card => {
        card.addEventListener('click', function() {
            const playlistId = this.dataset.id;
            const modal = document.getElementById(`playlist-modal-${playlistId}`);
            if (modal) {
                modal.style.display = 'block';
            }
        });
    });

    // Open album modal
    albumCards.forEach(card => {
        card.addEventListener('click', function() {
            const albumId = this.dataset.id;
            const modal = document.getElementById(`album-modal-${albumId}`);
            if (modal) {
                modal.style.display = 'block';
            }
        });
    });

    // Close modals
    modals.forEach(modal => {
        const closeBtn = modal.querySelector('.close-modal');
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                modal.style.display = 'none';
            });
        }
        // Close modal when clicking outside
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });
    });
});

// Star rating system
document.addEventListener('DOMContentLoaded', function() {
    // Handle the review form submission
    const reviewForm = document.querySelector('.review-form form');
    
    if (reviewForm) {
        reviewForm.addEventListener('submit', function(e) {
            // Check if a rating is selected
            const ratingInputs = document.querySelectorAll('input[name="rating"]');
            let isRatingSelected = false;
            
            ratingInputs.forEach(input => {
                if (input.checked) {
                    isRatingSelected = true;
                }
            });
            
            if (!isRatingSelected) {
                e.preventDefault();
                alert('Please select a rating');
            }
            
            // Additional validation can be added here
        });
        
        // Visual feedback for star selection
        const stars = document.querySelectorAll('.rating-selector label');
        
        stars.forEach(star => {
            star.addEventListener('click', function() {
                const forAttr = this.getAttribute('for');
                const ratingValue = forAttr.replace('star', '');
                document.getElementById(forAttr).checked = true;
            });
        });
    }
});
// Replace ALL dark mode toggle code in main.js with just this:
document.addEventListener('DOMContentLoaded', function() {
    const darkModeToggle = document.querySelector('.btn-theme');
    
    if(darkModeToggle) {
        darkModeToggle.addEventListener('click', function() {
            // Toggle the dark-mode class on the body
            document.body.classList.toggle('dark-mode');
            
            // Update the button icon and text
            const isDarkMode = document.body.classList.contains('dark-mode');
            const iconElement = this.querySelector('i');
            
            if(isDarkMode) {
                iconElement.classList.remove('fa-moon');
                iconElement.classList.add('fa-sun');
                this.textContent = ' Light Mode';
                this.prepend(iconElement);
            } else {
                iconElement.classList.remove('fa-sun');
                iconElement.classList.add('fa-moon');
                this.textContent = ' Dark Mode';
                this.prepend(iconElement);
            }
            
            // Save the preference via AJAX
            const xhr = new XMLHttpRequest();
            xhr.open('POST', window.location.href, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.send('toggle_dark_mode=' + (isDarkMode ? 1 : 0) + '&ajax=1');
        });
    }
});