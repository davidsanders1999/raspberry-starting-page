// IP-Adresse des Raspberry Pi Hosts (direkt gesetzt)
const PI_HOST_IP = '192.168.2.139';

// Ideenliste - wird aus localStorage geladen oder initialisiert
let ideas = [];

// Ideen aus localStorage laden
function loadIdeas() {
    const storedIdeas = localStorage.getItem('raspberry-pi-ideas');
    if (storedIdeas) {
        try {
            ideas = JSON.parse(storedIdeas);
        } catch (e) {
            console.error('Fehler beim Laden der Ideen:', e);
            ideas = [];
        }
    }
}

// Ideen in localStorage speichern
function saveIdeas() {
    localStorage.setItem('raspberry-pi-ideas', JSON.stringify(ideas));
}

// Konfiguration der Dienste - einfach erweiterbar!
const services = [
    {
        name: 'Portainer',
        icon: '🎛️',
        urls: [
            {
                label: 'HTTPS Port',
                url: 'https://192.168.2.139:9443/',
                protocol: 'https'
            }
        ]
    },
    {
        name: 'N8n',
        icon: '⚙️',
        urls: [
            {
                label: 'HTTP Port',
                url: 'http://192.168.2.139:5678/',
                protocol: 'http'
            }
        ]
    },
    {
        name: 'Home Assistant',
        icon: '🏠',
        urls: [
            {
                label: 'HTTP Port',
                url: 'http://192.168.2.139:8123/',
                protocol: 'http'
            }
        ]
    },
    {
        name: 'Matter Server',
        icon: '🔌',
        urls: [
            {
                label: 'WebSocket Port',
                url: 'ws://192.168.2.139:5580/ws',
                protocol: 'ws',
                isWebSocket: true
            }
        ]
    },
    {
        name: 'Pi-hole',
        icon: '🕳️',
        urls: [
            {
                label: 'HTTP Port',
                url: 'http://192.168.2.139:83/admin',
                protocol: 'http'
            },
            {
                label: 'HTTPS Port',
                url: 'https://192.168.2.139:446/admin',
                protocol: 'https'
            },
            {
                label: 'DHCP Server Port',
                url: 'udp://192.168.2.139:67',
                protocol: 'udp',
                isUDP: true
            }
        ]
    },
    {
        name: 'Nginx Proxy Manager',
        icon: '🔗',
        urls: [
            {
                label: 'HTTP Port',
                url: 'http://192.168.2.139:80/',
                protocol: 'http'
            },
            {
                label: 'HTTPS Port',
                url: 'https://192.168.2.139:443/',
                protocol: 'https'
            },
            {
                label: 'HTTP Admin',
                url: 'http://192.168.2.139:81/',
                protocol: 'http',
                isUDP: true
            }
        ]
    }
];

// Weitere Links
const otherLinks = [
    {
        name: 'Router',
        icon: '📡',
        urls: [
            {
                label: 'HTTP Port',
                url: 'http://192.168.2.1/',
                protocol: 'http'
            }
        ]
    }
];

// Funktion zum Erstellen einer Link-Karte
function createLinkCard(link, piIP = null) {
    // URLs verarbeiten
    const processedUrls = link.urls.map(urlConfig => {
        let displayUrl = urlConfig.url;
        
        // Für URLs mit der Pi-IP die IP dynamisch ersetzen
        if (piIP && piIP !== 'Nicht verfügbar' && urlConfig.url.includes('192.168.2.139')) {
            displayUrl = urlConfig.url.replace('192.168.2.139', piIP);
        }
        
        const protocolClass = urlConfig.protocol === 'https' ? 'protocol-https' : 
                           urlConfig.protocol === 'ws' ? 'protocol-ws' : 
                           urlConfig.protocol === 'udp' ? 'protocol-udp' : 'protocol-http';
        const protocolText = urlConfig.protocol.toUpperCase();
        
        return {
            ...urlConfig,
            displayUrl,
            protocolClass,
            protocolText
        };
    });
    
    // Prüfen ob alle URLs WebSocket sind
    const allWebSocket = processedUrls.every(u => u.isWebSocket);
    
    // URL-Bereich rendern
    const urlSections = processedUrls.map(url => {
        if (url.isWebSocket || url.isUDP) {
            const labelHtml = url.isUDP ? `<div class="url-label">${url.label}</div>` : '';
            return `
                <div class="url-item">
                    ${labelHtml}
                    <div class="link-url ws-url">
                        ${url.displayUrl}
                        <span class="protocol-badge ${url.protocolClass}">${url.protocolText}</span>
                    </div>
                </div>
            `;
        } else {
            return `
                <div class="url-item">
                    <a href="${url.displayUrl}" target="_blank" class="link-url" onclick="event.stopPropagation()">
                        ${url.displayUrl}
                        <span class="protocol-badge ${url.protocolClass}">${url.protocolText}</span>
                    </a>
                </div>
            `;
        }
    }).join('');
    
    // Karte rendern
    if (allWebSocket) {
        return `
            <div class="link-card ws-card">
                <div class="link-header">
                    <div class="link-icon">${link.icon}</div>
                    <div class="link-name">${link.name}</div>
                </div>
                <div class="url-section">
                    ${urlSections}
                </div>
            </div>
        `;
    }
    
    return `
        <div class="link-card">
            <div class="link-header">
                <div class="link-icon">${link.icon}</div>
                <div class="link-name">${link.name}</div>
            </div>
            <div class="url-section">
                ${urlSections}
            </div>
        </div>
    `;
}


// Links rendern
function renderLinks(piIP = null) {
    const servicesGrid = document.getElementById('services-grid');
    const otherLinksGrid = document.getElementById('other-links-grid');

    servicesGrid.innerHTML = services.map(link => createLinkCard(link, piIP)).join('');
    otherLinksGrid.innerHTML = otherLinks.map(link => createLinkCard(link, piIP)).join('');
}

// IP-Adresse anzeigen
function displayPiIP() {
    document.getElementById('pi-ip').textContent = PI_HOST_IP;
}

// Datum anzeigen
function updateDate() {
    const dateElement = document.getElementById('current-date');
    const now = new Date();
    dateElement.textContent = now.toLocaleDateString('de-DE', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Ideenliste rendern
function renderIdeas() {
    const ideasList = document.getElementById('ideas-list');
    
    if (ideas.length === 0) {
        ideasList.innerHTML = '<div class="empty-ideas">Keine Ideen vorhanden. Füge eine neue Idee hinzu!</div>';
        return;
    }

    ideasList.innerHTML = ideas.map(idea => `
        <div class="idea-card">
            <div class="idea-header">
                <div class="idea-title">${escapeHtml(idea.title)}</div>
                <button class="idea-remove-btn" onclick="removeIdea(${idea.id})">✕ Entfernen</button>
            </div>
            <div class="idea-description">${escapeHtml(idea.description)}</div>
        </div>
    `).join('');
}

// HTML-Escape-Funktion zur Sicherheit
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Idee hinzufügen
function addIdea(title, description) {
    const newIdea = {
        id: Date.now(),
        title: title.trim(),
        description: description.trim()
    };
    
    ideas.push(newIdea);
    saveIdeas();
    renderIdeas();
}

// Idee entfernen
function removeIdea(id) {
    if (confirm('Möchtest du diese Idee wirklich entfernen?')) {
        ideas = ideas.filter(idea => idea.id !== id);
        saveIdeas();
        renderIdeas();
    }
}

// Formular ein-/ausblenden
function toggleIdeaForm() {
    const formContainer = document.getElementById('idea-form-container');
    const toggleBtn = document.getElementById('idea-toggle-btn');
    
    if (formContainer.style.display === 'none') {
        formContainer.style.display = 'block';
        toggleBtn.textContent = '✕ Abbrechen';
        // Fokus auf Titel-Feld setzen
        setTimeout(() => {
            document.getElementById('idea-title').focus();
        }, 100);
    } else {
        formContainer.style.display = 'none';
        toggleBtn.textContent = '➕ Idee hinzufügen';
        // Formular zurücksetzen
        document.getElementById('idea-form').reset();
    }
}

// Initialisierung
document.addEventListener('DOMContentLoaded', () => {
    displayPiIP();
    renderLinks(PI_HOST_IP);
    updateDate();
    
    // Ideen laden und rendern
    loadIdeas();
    renderIdeas();
    
    // Formular-Event-Handler
    const ideaForm = document.getElementById('idea-form');
    if (ideaForm) {
        ideaForm.addEventListener('submit', (e) => {
            e.preventDefault();
            
            const titleInput = document.getElementById('idea-title');
            const descriptionInput = document.getElementById('idea-description');
            
            const title = titleInput.value;
            const description = descriptionInput.value;
            
            if (title && description) {
                addIdea(title, description);
                // Formular schließen
                toggleIdeaForm();
            }
        });
    }
});

