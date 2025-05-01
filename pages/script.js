document.addEventListener("DOMContentLoaded", () => {
    const modelSelect = document.getElementById("modelSelect");
    const ctx = document.getElementById("predictionChart").getContext("2d");
    let chart = null;

    // Function to fetch and display data
    async function fetchAndDisplayChart(model) {
        try {
            const response = await fetch(`load_predictions.php?model=${model}`);
            if (!response.ok) throw new Error("Failed to load data");

            const data = await response.json();

            // Extract data for the chart
            const categories = data.map(item => item.category);
            const predictions = data.map(item => item.prediction);

            // Update or create the chart
            if (chart) {
                chart.data.labels = categories;
                chart.data.datasets[0].data = predictions;
                chart.update();
            } else {
                chart = new Chart(ctx, {
                    type: "bar", // You can change this to "line", "pie", etc.
                    data: {
                        labels: categories,
                        datasets: [{
                            label: `Predicted Expenses (${model.replace("_", " ")})`,
                            data: predictions,
                            backgroundColor: "rgba(75, 192, 192, 0.2)",
                            borderColor: "rgba(75, 192, 192, 1)",
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
        } catch (error) {
            console.error(error);
            alert("Failed to load chart data.");
        }
    }

    // Initial load
    fetchAndDisplayChart(modelSelect.value);

    // Update chart on model selection change
    modelSelect.addEventListener("change", () => {
        fetchAndDisplayChart(modelSelect.value);
    });
});
