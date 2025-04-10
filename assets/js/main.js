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

    // Dark mode toggle
    const darkModeToggle = document.querySelector('.btn-theme');
    
    if(darkModeToggle) {
        darkModeToggle.addEventListener('click', function() {
            document.body.classList.toggle('dark-mode');
        });
    }
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