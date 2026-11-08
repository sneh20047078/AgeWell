/**
 * AgeWell Senior Care and Support System
 * Chart creation and visualization functions
 */

/**
 * Create a line chart
 * 
 * @param {string} canvasId Canvas element ID
 * @param {array} labels Chart labels (x-axis)
 * @param {array} datasets Chart datasets
 * @param {string} title Chart title
 */
function createLineChart(canvasId, labels, datasets, title = '') {
    const ctx = document.getElementById(canvasId).getContext('2d');
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: title !== '',
                    text: title,
                    font: {
                        size: 16,
                        weight: 'bold'
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                },
                legend: {
                    display: true,
                    position: 'top'
                }
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Date'
                    }
                },
                y: {
                    display: true,
                    beginAtZero: false
                }
            }
        }
    });
}

/**
 * Create a bar chart
 * 
 * @param {string} canvasId Canvas element ID
 * @param {array} labels Chart labels (x-axis)
 * @param {array} datasets Chart datasets
 * @param {string} title Chart title
 */
function createBarChart(canvasId, labels, datasets, title = '') {
    const ctx = document.getElementById(canvasId).getContext('2d');
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: title !== '',
                    text: title,
                    font: {
                        size: 16,
                        weight: 'bold'
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                },
                legend: {
                    display: true,
                    position: 'top'
                }
            },
            scales: {
                x: {
                    display: true
                },
                y: {
                    display: true,
                    beginAtZero: true
                }
            }
        }
    });
}

/**
 * Create a pie chart
 * 
 * @param {string} canvasId Canvas element ID
 * @param {array} labels Chart labels
 * @param {array} data Chart data
 * @param {array} colors Background colors
 * @param {string} title Chart title
 */
function createPieChart(canvasId, labels, data, colors, title = '') {
    const ctx = document.getElementById(canvasId).getContext('2d');
    
    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: colors,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: title !== '',
                    text: title,
                    font: {
                        size: 16,
                        weight: 'bold'
                    }
                },
                legend: {
                    display: true,
                    position: 'top'
                }
            }
        }
    });
}

/**
 * Create a doughnut chart
 * 
 * @param {string} canvasId Canvas element ID
 * @param {array} labels Chart labels
 * @param {array} data Chart data
 * @param {array} colors Background colors
 * @param {string} title Chart title
 */
function createDoughnutChart(canvasId, labels, data, colors, title = '') {
    const ctx = document.getElementById(canvasId).getContext('2d');
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: colors,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: title !== '',
                    text: title,
                    font: {
                        size: 16,
                        weight: 'bold'
                    }
                },
                legend: {
                    display: true,
                    position: 'top'
                }
            }
        }
    });
}

/**
 * Create a blood pressure chart (dual y-axis)
 * 
 * @param {string} canvasId Canvas element ID
 * @param {array} labels Date labels
 * @param {array} systolicData Systolic pressure data
 * @param {array} diastolicData Diastolic pressure data
 */
function createBloodPressureChart(canvasId, labels, systolicData, diastolicData) {
    const ctx = document.getElementById(canvasId).getContext('2d');
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Systolic',
                    data: systolicData,
                    borderColor: 'rgb(255, 99, 132)',
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    fill: false,
                    tension: 0.1
                },
                {
                    label: 'Diastolic',
                    data: diastolicData,
                    borderColor: 'rgb(54, 162, 235)',
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    fill: false,
                    tension: 0.1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Blood Pressure Over Time',
                    font: {
                        size: 16,
                        weight: 'bold'
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Date'
                    }
                },
                y: {
                    display: true,
                    title: {
                        display: true,
                        text: 'mmHg'
                    },
                    suggestedMin: 40,
                    suggestedMax: 180
                }
            }
        }
    });
}

/**
 * Create a weight tracking chart
 * 
 * @param {string} canvasId Canvas element ID
 * @param {array} labels Date labels
 * @param {array} weightData Weight data
 */
function createWeightChart(canvasId, labels, weightData) {
    const ctx = document.getElementById(canvasId).getContext('2d');
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Weight (kg)',
                    data: weightData,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    fill: true,
                    tension: 0.1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Weight Over Time',
                    font: {
                        size: 16,
                        weight: 'bold'
                    }
                }
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Date'
                    }
                },
                y: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Weight (kg)'
                    }
                }
            }
        }
    });
}

/**
 * Create a heart rate chart
 * 
 * @param {string} canvasId Canvas element ID
 * @param {array} labels Date labels
 * @param {array} heartRateData Heart rate data
 */
function createHeartRateChart(canvasId, labels, heartRateData) {
    const ctx = document.getElementById(canvasId).getContext('2d');
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Heart Rate (bpm)',
                    data: heartRateData,
                    borderColor: 'rgb(255, 99, 132)',
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    fill: true,
                    tension: 0.1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Heart Rate Over Time',
                    font: {
                        size: 16,
                        weight: 'bold'
                    }
                }
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Date'
                    }
                },
                y: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Heart Rate (bpm)'
                    },
                    suggestedMin: 40,
                    suggestedMax: 120
                }
            }
        }
    });
}

/**
 * Create a blood sugar chart
 * 
 * @param {string} canvasId Canvas element ID
 * @param {array} labels Date labels
 * @param {array} bloodSugarData Blood sugar data
 */
function createBloodSugarChart(canvasId, labels, bloodSugarData) {
    const ctx = document.getElementById(canvasId).getContext('2d');
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Blood Sugar (mg/dL)',
                    data: bloodSugarData,
                    borderColor: 'rgb(255, 159, 64)',
                    backgroundColor: 'rgba(255, 159, 64, 0.2)',
                    fill: true,
                    tension: 0.1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Blood Sugar Over Time',
                    font: {
                        size: 16,
                        weight: 'bold'
                    }
                }
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Date'
                    }
                },
                y: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Blood Sugar (mg/dL)'
                    }
                }
            }
        }
    });
}

/**
 * Create a temperature chart
 * 
 * @param {string} canvasId Canvas element ID
 * @param {array} labels Date labels
 * @param {array} temperatureData Temperature data
 */
function createTemperatureChart(canvasId, labels, temperatureData) {
    const ctx = document.getElementById(canvasId).getContext('2d');
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Temperature (°F)',
                    data: temperatureData,
                    borderColor: 'rgb(153, 102, 255)',
                    backgroundColor: 'rgba(153, 102, 255, 0.2)',
                    fill: true,
                    tension: 0.1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Temperature Over Time',
                    font: {
                        size: 16,
                        weight: 'bold'
                    }
                }
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Date'
                    }
                },
                y: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Temperature (°F)'
                    },
                    suggestedMin: 95,
                    suggestedMax: 105
                }
            }
        }
    });
}

/**
 * Create a medication adherence chart
 * 
 * @param {string} canvasId Canvas element ID
 * @param {array} labels Medication names
 * @param {array} adherenceData Adherence percentages
 */
function createMedicationAdherenceChart(canvasId, labels, adherenceData) {
    const ctx = document.getElementById(canvasId).getContext('2d');
    
    // Generate colors for each medication
    const colors = generateColors(labels.length);
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Adherence (%)',
                    data: adherenceData,
                    backgroundColor: colors,
                    borderColor: colors.map(color => color.replace('0.2', '1')),
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Medication Adherence',
                    font: {
                        size: 16,
                        weight: 'bold'
                    }
                }
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Medication'
                    }
                },
                y: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Adherence (%)'
                    },
                    min: 0,
                    max: 100
                }
            }
        }
    });
}

/**
 * Generate an array of colors for charts
 * 
 * @param {number} count Number of colors to generate
 * @return {array} Array of RGBA color strings
 */
function generateColors(count) {
    const baseColors = [
        'rgba(255, 99, 132, 0.2)',  // red
        'rgba(54, 162, 235, 0.2)',  // blue
        'rgba(255, 206, 86, 0.2)',  // yellow
        'rgba(75, 192, 192, 0.2)',  // green
        'rgba(153, 102, 255, 0.2)', // purple
        'rgba(255, 159, 64, 0.2)',  // orange
        'rgba(199, 199, 199, 0.2)'  // gray
    ];
    
    // If we need more colors than our base set, generate them
    if (count <= baseColors.length) {
        return baseColors.slice(0, count);
    } else {
        let colors = [...baseColors];
        
        for (let i = baseColors.length; i < count; i++) {
            // Generate random RGB values
            const r = Math.floor(Math.random() * 256);
            const g = Math.floor(Math.random() * 256);
            const b = Math.floor(Math.random() * 256);
            
            colors.push(`rgba(${r}, ${g}, ${b}, 0.2)`);
        }
        
        return colors;
    }
}
