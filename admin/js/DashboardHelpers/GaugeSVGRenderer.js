class ValservGaugeSVGRenderer {
  constructor(containerId) {
    this.container = document.getElementById(containerId);
  }

  // Helper function to interpolate between two colors
  _interpolateColor(color1, color2, factor) {
    const result = color1.slice(); // Clone color1 to modify
    for (let i = 0; i < 3; i++) {
      result[i] = Math.round(result[i] + factor * (color2[i] - color1[i]));
    }
    return `rgb(${result[0]}, ${result[1]}, ${result[2]})`;
  }

  // Helper function to convert hex color to RGB array
  _hexToRgb(hex) {
    const bigint = parseInt(hex.slice(1), 16);
    const r = (bigint >> 16) & 255;
    const g = (bigint >> 8) & 255;
    const b = bigint & 255;
    return [r, g, b];
  }

  // Modified render method to accept date arrays and granularity
  render(data, metrics, requestedDates = [], comparisonData = [], comparisonDates = [], granularity = 'daily') {
    if (!this.container || !metrics?.length) return; // Data can be empty if no results for a date
    this.container.innerHTML = '';
    
    // Center layout if only one gauge block (typically daily)
    if (granularity !== 'hourly' || requestedDates.length < 2) {
      this.container.style.textAlign = 'center';
    } else {
      this.container.style.textAlign = ''; // Reset in case of switching
    }

    const setsToRender = [];

    const compareEnabled = document.getElementById('compare-toggle')?.checked;
    const isCompare = compareEnabled && comparisonData?.length && comparisonDates?.length;


    if (granularity === 'hourly' && requestedDates.length === 2) {
      setsToRender.push({
        date: requestedDates[0],
        data: data.filter(row => row.date.startsWith(requestedDates[0]))
      });
      setsToRender.push({
        date: requestedDates[1],
        data: data.filter(row => row.date.startsWith(requestedDates[1]))
      });
    } else if (isCompare) {
      setsToRender.push({
        date: `Primary (${requestedDates[0]} to ${requestedDates.at(-1)})`,
        data: data
      });
      setsToRender.push({
        date: `Compare (${comparisonDates[0]} to ${comparisonDates.at(-1)})`,
        data: comparisonData
      });
    } else {
      const displayDate = requestedDates.length === 1
        ? requestedDates[0]
        : `Engagement from ${requestedDates[0]} to ${requestedDates.at(-1)}`;
      setsToRender.push({ date: displayDate, data: data });
    }



    this.container.style.textAlign = setsToRender.length === 1 ? 'center' : '';


    setsToRender.forEach(({ date: currentDateLabel, data: currentDataSet }) => {
      // Create a section for each date's gauges
      const dateSection = document.createElement('div');
      dateSection.className = 'gauge-date-section';
      if (setsToRender.length === 2) {
        dateSection.classList.add('double'); // Only apply when two blocks are being rendered (hourly compare)
      }

      this.container.appendChild(dateSection);

      // Add the date as a title for the section
      const dateTitle = document.createElement('h3');
      dateTitle.className = 'gauge-date-title';
      dateTitle.textContent = `Engagement for ${currentDateLabel}`;

      dateSection.appendChild(dateTitle);

      const gaugesWrapper = document.createElement('div');
      gaugesWrapper.className = 'gauge-cards-wrapper'; // To allow flexbox for gauges
      dateSection.appendChild(gaugesWrapper);

      // Calculate engagement for the current date's data
      const engagement = this._calculateEngagement(currentDataSet, metrics);

      metrics.forEach((metric) => {
        const value = engagement[metric];
        const fillValue = Math.min(value, 100); // Value for gauge fill, capped at 100

        const strokeWidth = 15;
        const radius = 60;
        const center = radius + strokeWidth;
        const svgSize = (radius + strokeWidth) * 2;

        const circumference = 2 * Math.PI * radius; // Full circumference for a full circle
        const dashOffset = circumference * (1 - fillValue / 100);

        let strokeColor;

        // Lock color logic to specific metrics by name
        if (metric === 'averageEngagedDuration' || metric === 'averageEngagedDepth') {
        const percent = Math.max(0, Math.min(fillValue, 100)); // Ensure percent is between 0 and 100

        let hue;
        const saturation = 80; // You can adjust this for more vividness/dullness
        const lightness = 45; // You can adjust this for brighter/darker colors

        if (percent <= 50) {
            // From 0% to 50%: Red -> Orange -> Yellow -> Light Green
            // We'll break this down into smaller segments for better control

            if (percent <= 12.5) { // 0% to 12.5% (Red to Orange)
                // Hue for Red is 0, Hue for Orange is approximately 30
                hue = (percent / 12.5) * 30;
            } else if (percent <= 25) { // 12.5% to 25% (Orange to Yellow)
                // Hue for Orange is 30, Hue for Yellow is 60
                hue = 30 + ((percent - 12.5) / 12.5) * 30;
            } else if (percent <= 50) { // 25% to 50% (Yellow to Light Green)
                // Hue for Yellow is 60, Hue for Light Green is approximately 90
                hue = 60 + ((percent - 25) / 25) * 30;
            }

        } else {
            // From 50% to 100%: Light Green -> Dark Green (Forest Green)
            // Light Green hue is approx 90, Forest Green hue is approx 120
            hue = 90 + ((percent - 50) / 50) * 30;
        }

        strokeColor = `hsl(${hue}, ${saturation}%, ${lightness}%)`;
    }

      else { // 'averageConnectionSpeed' or any other metric will use this default
          strokeColor = '#8bc34a'; // Fixed light green
        }

        const displayUnit = this._unit(metric);
        const displayText = `${value.toFixed(0)}${displayUnit}`; // Format value and append unit

        const svg = `
          <svg width="${svgSize}" height="${svgSize}" viewBox="0 0 ${svgSize} ${svgSize}">
            <circle
              cx="${center}" cy="${center}" r="${radius}"
              stroke="#e5e7eb" stroke-width="${strokeWidth}"
              fill="none"
            />

            <circle
              cx="${center}" cy="${center}" r="${radius}"
              stroke="${strokeColor}" stroke-width="${strokeWidth}"
              fill="none"
              stroke-linecap="round"
              stroke-dasharray="${circumference}"
              stroke-dashoffset="${circumference}"
              transform="rotate(-90 ${center} ${center})">
              <animate
                attributeName="stroke-dashoffset"
                from="${circumference}"
                to="${dashOffset}"
                dur="1s"
                fill="freeze"
                begin="0s"
            />
            </circle>

            <text x="${center}" y="${center + 8}" text-anchor="middle" font-size="20" font-weight="bold" fill="#111">
              ${displayText}
            </text>
          </svg>
        `;

        gaugesWrapper.innerHTML += `
          <div class="gauge-card">
            ${svg}
            <div class="gauge-label">${this._label(metric)}</div>
            <div class="gauge-unit"></div>
          </div>
        `;
      });
    });
  }

  _calculateEngagement(data, metrics) {
    const totals = {}, counts = {};

    metrics.forEach(m => {
      const normalized = m.toLowerCase();
      totals[normalized] = 0;
      counts[normalized] = 0;

      data.forEach(row => {
        const val = parseFloat(row[normalized]);
        if (!isNaN(val)) {
          totals[normalized] += val;
          counts[normalized] += 1;
        }
      });
    });

    const avg = {};
    metrics.forEach(m => {
      const normalized = m.toLowerCase(); // ✅ fix here
      avg[m] = counts[normalized] ? totals[normalized] / counts[normalized] : 0;
    });

    return avg;
  }




  _label(metric) {
    const map = {
      averageengagedduration: 'Avg. Engaged Duration',
      averageengageddepth: 'Avg. Engaged Depth',
      averageconnectionspeed: 'Avg. Connection Speed',
      pagespersession: 'Avg. Pages / Session'
    };
    const normalized = this._normalizeKey(metric).toLowerCase();
    return map[normalized] || metric;

  }


  _unit(metric) {
    const key = this._normalizeKey(metric).toLowerCase();
    if (key === 'averageengagedduration') return ' sec';
    if (key === 'averageengageddepth') return '%';
    if (key === 'averageconnectionspeed') return ' mb/s';
    return '';
  }

  _normalizeKey(key) {
    const map = {
      averageengagedduration: 'averageEngagedDuration',
      averageengageddepth: 'averageEngagedDepth',
      averageconnectionspeed: 'averageConnectionSpeed',
      pagespersession: 'pagesPerSession'
    };
    return map[key.toLowerCase()] || key;
  }


}

window.GaugeSVGRenderer = ValservGaugeSVGRenderer;