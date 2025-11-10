// DashboardHelpers/ChartRenderer.js

export default class ChartRenderer {
    static colorMap = {
        'sessions': '#1976d2',
        'visits': '#e53935',
        'views': '#7e57c2',
        'bounce_rate': '#43a047',
        'comparison': '#bdbdbd',
        'comparisonHighlight': '#ff9800'
    };
    
    constructor(colorMapOverride = null) {
        this.chart = null;
        this.colorMap = colorMapOverride || ChartRenderer.colorMap;
    }

    // Render chart using Chart.js
    renderChart({ chartDivId, data, comparisonData, metric, getMetricDisplayName }) {
        
        // Dispose previous chart if it exists
        this.disposeChart();

        let chartContainer = document.getElementById(chartDivId);
        if (!chartContainer) return;
        
        if (!window.Chart) {
            console.error('Chart.js is not loaded');
            return;
        }
        
        // Get or create canvas element
        let chartCanvas = chartContainer.querySelector('canvas');
        if (!chartCanvas) {
            chartCanvas = document.createElement('canvas');
            chartContainer.innerHTML = '';
            chartContainer.appendChild(chartCanvas);
        }
        
        // Get 2D context
        const ctx = chartCanvas.getContext('2d');
        if (!ctx) {
            console.error('Could not get canvas context');
            return;
        }
        
        const hasComparison = comparisonData && comparisonData.length > 0;
        
        // Prepare datasets
        const datasets = [];
        
        // Main dataset - convert data to Chart.js format {x: date, y: value}
        const mainColor = this.colorMap[metric] || '#1976d2';
        const chartData = (data || []).map(item => ({
            x: new Date(item.date),
            y: item[metric] || 0
        }));
        
        datasets.push({
            label: getMetricDisplayName(metric) + (hasComparison ? ' (Current)' : ''),
            data: chartData,
            borderColor: mainColor,
            backgroundColor: mainColor + '20',
            borderWidth: 2,
            fill: false,
            tension: 0.4,
            pointRadius: 2.5,
            pointHoverRadius: 5,
            pointBackgroundColor: mainColor,
            pointBorderColor: '#fff',
            pointBorderWidth: 1.5,
            pointStyle: 'circle'
        });
        
        // Comparison dataset
        if (hasComparison) {
            const comparisonColor = this.colorMap.comparisonHighlight || '#ff9800';
            const comparisonChartData = (comparisonData || []).map(item => ({
                x: new Date(item.date),
                y: item[metric] || 0
            }));
            
            datasets.push({
                label: getMetricDisplayName(metric) + ' (Comparison)',
                data: comparisonChartData,
                borderColor: comparisonColor,
                backgroundColor: comparisonColor + '20',
                borderWidth: 2,
                borderDash: [8, 4],
                fill: false,
                tension: 0.4,
                pointRadius: 2.5,
                pointHoverRadius: 5,
                pointBackgroundColor: comparisonColor,
                pointBorderColor: '#fff',
                pointBorderWidth: 1.5,
                pointStyle: 'circle'
            });
        }
        
        // Create chart
        this.chart = new Chart(ctx, {
            type: 'line',
            data: {
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            usePointStyle: false,
                            padding: 15,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.1)',
                        borderWidth: 1,
                        padding: 10,
                        displayColors: true,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: 'day',
                            displayFormats: {
                                day: 'MMM dd'
                            },
                            tooltipFormat: 'MMM dd, yyyy'
                        },
                        adapters: {
                            date: {
                                library: 'date-fns'
                            }
                        },
                        grid: {
                            display: true,
                            color: 'rgba(0, 0, 0, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            display: true,
                            color: 'rgba(0, 0, 0, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }

    disposeChart() {
        if (this.chart && typeof this.chart.destroy === 'function') {
            this.chart.destroy();
            this.chart = null;
        }
    }

    async exportChartToPDF(chartDivId) {
        // Libraries should be enqueued via wp_enqueue_script in PHP
        // Check if libraries are available
        if (typeof html2canvas === 'undefined') {
            console.error('html2canvas is not loaded. Please ensure it is enqueued via wp_enqueue_script.');
            alert('PDF export requires html2canvas library. Please refresh the page.');
            return;
        }
        if (typeof window.jspdf === 'undefined' && typeof jsPDF === 'undefined') {
            console.error('jsPDF is not loaded. Please ensure it is enqueued via wp_enqueue_script.');
            alert('PDF export requires jsPDF library. Please refresh the page.');
            return;
        }
        const chartDiv = document.getElementById(chartDivId);
        if (!chartDiv) {
            alert('Chart not found!');
            return;
        }
        const canvasImage = await html2canvas(chartDiv, {backgroundColor: '#fff'});
        const imgData = canvasImage.toDataURL('image/png');
        const PDFClass = window.jspdf?.jsPDF || window.jsPDF;
        if (!PDFClass) {
            alert('jsPDF not loaded!');
            return;
        }
        // Scale to A4 landscape (842pt wide)
        const pageWidth = 842;
        const scale = pageWidth / canvasImage.width;
        const pageHeight = canvasImage.height * scale;
        const pdf = new PDFClass({
            orientation: 'landscape',
            unit: 'pt',
            format: [pageWidth, pageHeight]
        });
        pdf.addImage(imgData, 'PNG', 0, 0, pageWidth, pageHeight);
        pdf.save('chart.pdf');
    }

    async exportChartAndTableToPDF(chartDivId, tableId, tableHeaderSelector) {
        // Libraries should be enqueued via wp_enqueue_script in PHP
        // Check if libraries are available
        if (typeof html2canvas === 'undefined') {
            console.error('html2canvas is not loaded. Please ensure it is enqueued via wp_enqueue_script.');
            alert('PDF export requires html2canvas library. Please refresh the page.');
            return;
        }
        if (typeof window.jspdf === 'undefined' && typeof jsPDF === 'undefined') {
            console.error('jsPDF is not loaded. Please ensure it is enqueued via wp_enqueue_script.');
            alert('PDF export requires jsPDF library. Please refresh the page.');
            return;
        }
        const chartDiv = document.getElementById(chartDivId);
        const table = document.getElementById(tableId);
        const tableHeader = document.querySelector(tableHeaderSelector);
        if (!chartDiv || !table) {
            alert('Chart or table not found!');
            return;
        }
        const tempWrapper = document.createElement('div');
        if (tableHeader) {
            tempWrapper.appendChild(tableHeader.cloneNode(true));
        }
        tempWrapper.appendChild(table.cloneNode(true));
        tempWrapper.style.background = '#fff';
        tempWrapper.style.padding = '16px';
        tempWrapper.style.width = table.offsetWidth + 'px';
        document.body.appendChild(tempWrapper);
        const chartCanvas = await html2canvas(chartDiv, {backgroundColor: '#fff'});
        const tableCanvas = await html2canvas(tempWrapper, {backgroundColor: '#fff'});
        const chartImg = chartCanvas.toDataURL('image/png');
        const tableImg = tableCanvas.toDataURL('image/png');
        document.body.removeChild(tempWrapper);
        const PDFClass = window.jspdf?.jsPDF || window.jsPDF;
        if (!PDFClass) {
            alert('jsPDF not loaded!');
            return;
        }
        // Scale to A4 landscape (842pt wide)
        const pageWidth = 842;
        const chartScale = pageWidth / chartCanvas.width;
        const tableScale = pageWidth / tableCanvas.width;
        const chartHeight = chartCanvas.height * chartScale;
        const tableHeight = tableCanvas.height * tableScale;
        const pdfHeight = chartHeight + tableHeight;
        const pdf = new PDFClass({
            orientation: 'landscape',
            unit: 'pt',
            format: [pageWidth, pdfHeight]
        });
        pdf.addImage(chartImg, 'PNG', 0, 0, pageWidth, chartHeight);
        pdf.addImage(tableImg, 'PNG', 0, chartHeight, pageWidth, tableHeight);
        pdf.save('chart-table.pdf');
    }
}
